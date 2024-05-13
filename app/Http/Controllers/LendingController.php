<?php

namespace App\Http\Controllers;

use App\Models\StuffStock;
use Illuminate\Http\Request;
use App\Helpers\ApiFormatter;
use App\Models\Lending;


class LendingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index() {
        try {
            //kalo ada with cek nya itu di relasinya yg ada di model sebelum with, ambil nama functionnya       

            $data = Lending::with('stuff', 'user', 'restoration')->get();
                return ApiFormatter::sendResponse(200, 'succes', $data);
        } catch (\Exception $err) {
            return ApiFormatter::sendResponse(400, 'bad request', $err->getMessage());
        }
    }

    public function store(Request $request) {
        try {
            $this->validate($request, [
                'stuff_id' => 'required',
                'date_time' => 'required',
                'name' => 'required',
                'total_stuff' => 'required',
            ]);
            //user_id tidak masuk ke validasi karena valuenya bukan bersumber dari luar (dipilih user)

            //cek total_available stuff terkait
            $totalAvailable = StuffStock::where('stuff_id', $request->stuff_id)->value('total_available');
             
            if (is_null($totalAvailable)) {
                return ApiFormatter::sendResponse(400, 'bad request', 'Belum ada data inbound !');
            } elseif ((int)$request->total_stuff > (int)$totalAvailable) {
                return ApiFormatter::sendResponse(400, 'bad request', 'Stock tidak tersedia !');
            } else {
                $lending = Lending::create([
                    'stuff_id' => $request->stuff_id,
                    'date_time' => $request->date_time,
                    'name' => $request->name,
                    'notes' => $request->notes ? $request->notes : '-',
                    'total_stuff' => $request->total_stuff,
                    'user_id' => auth()->user()->id,
                ]);

                $totalAvailableNow = (int)$totalAvailable - (int)$request->total_stuff;
                $StuffStock = StuffStock::where('stuff_id', $request->stuff_id)->update(['total_available' => $totalAvailableNow]);

                $dataLending = Lending::where('id', $lending['id'])->with('user', 'stuff', 'stuff.StuffStock')->first();

                return ApiFormatter::sendResponse(200, 'succes', $dataLending);
            }
        } catch (\Exception $err) {
            return ApiFormatter::sendResponse(400, 'bad request', $err->getMessage());
        }
    }

    public function destroy($id){
        try{
            // Mencari data peminjaman berdasarkan ID
            $lending = Lending::where('id', $id)->first();
    
            // Jika data peminjaman ditemukan
            if($lending) {
                // Periksa apakah peminjaman memiliki data restorasi terkait
                if($lending->restoration()->exists()){
                    return ApiFormatter::sendResponse(400, 'bad request', 'Data peminjaman sudah memiliki pengembalian barang');
                } else {
                    // Mengembalikan jumlah total barang yang dipinjam ke total yang tersedia dalam stok barang
                    $stuffStock = StuffStock::where('stuff_id', $lending->stuff_id)->first();
                    $stuffStock->total_available += $lending->total_stuff;
                    $stuffStock->save();
    
                    // Menghapus data peminjaman
                    $lending->delete();
    
                    // Ambil data terbaru setelah perubahan
                    $data = Lending::with('stuff', 'user', 'restoration')->get();
                   
                    // Kirim respons dengan data yang diperbarui
                    return ApiFormatter::sendResponse(200, 'success', $data);
                }
            } else {
                // Jika data peminjaman tidak ditemukan
                return ApiFormatter::sendResponse(404, 'bad request', 'Data peminjaman tidak ditemukan');
            }
        } catch (\Exception $err) {
            // Jika terjadi kesalahan selama proses
            return ApiFormatter::sendResponse(400, 'bad request', $err->getMessage());
        }
    }
}    