<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\Jurnal;
use App\Models\Akun;

class NeracaController extends Controller
{
    public function index($year = null){

        if($year == null) {
            $year = 'Y';
        }
        $dateawal = date($year.'-01-01 00:00:01');
        $dateakhir = date($year.'-12-31 23:59:59');

        $jenis = ['1','2','3'];
        $output = [];

        foreach ($jenis as $key => $value) {
            $nama = 'assets';
            if($value == '3'){
                $nama = 'equity';
            }
            if($value == '2'){
                $nama = 'liabilities';
            }
            $headsaldo = 0;
            $dd = DB::table('master_akun')
            ->where('deleted_at')
            ->where('header','=',0)
            ->where('komponen','=',null)
            ->where('jenis_akun_id','=',$value)    
            ->orderBy('kode_akun','ASC')
            ->get();
    
            foreach ($dd as $key => $sub) {
                $saldo = 0;
                $komponen = DB::table('master_akun')
                    ->where('komponen','=', $sub->kode_akun)
                    ->where('deleted_at')
                    ->orderBy('kode_akun','ASC');
                if($komponen->count() > 0){
                    $komponen = $komponen->get();
                    foreach ($komponen as $key => $komp) {
                        $komp->saldo = $this->cekSaldo($komp->id, $komp->saldo_normal, $year);
                        $saldo +=$komp->saldo;
                    }
                }else{
                    $komponen = null;
                }

                $sub->komponen = $komponen;
                $subsaldo = $this->cekSaldo($sub->id,$sub->saldo_normal, $year);
                $sub->saldo = $saldo + $subsaldo;
    
                $headsaldo += $sub->saldo;
            }

            $output[$nama] = $dd;
        }

        $laba = $this->labaditahan($year);
        $data = [
            'nama' => 'yaaa',
            'saldo' => 1000
        ];

        $output['equity'][]= $laba;
        return response()->json($output, 200);
    }

    public function labaditahan($year)
    {
        $cabang = 1;
        if($year == null) {
            $year = 'Y';
        }
        $dateawal = date($year.'-01-01 00:00:01');
        $dateakhir = date($year.'-12-31 23:59:59');

        $jenis = ['4','5'];
        $output = [];
        $totalPendapatan = 0;
        $totalBeban = 0;

        foreach ($jenis as $key => $value) {
            $nama = 'pendapatan';
            if($value == '5'){
                $nama = 'beban';
            }

            $dd = DB::table('master_akun')
            ->where('deleted_at')
            ->where('header','=',0)
            ->where('komponen','=',null)
            ->where('jenis_akun_id','=',$value)    
            ->orderBy('kode_akun','ASC')
            ->get();
    
            foreach ($dd as $key => $sub) {
                $headerSaldo = 0;
                $komponen = Akun::where('deleted_at')
                ->where('komponen', $sub->kode_akun)
                ->whereIn('cabang_id', array(0, $cabang))
                ->get();
                $sub->komponen = $komponen;

                foreach ($komponen as $key => $value) {
                    $saldo = $this->cekSaldo($value->id,$value->saldo_normal, $year, $cabang);
                    $value->saldo = $saldo;
                    $headerSaldo += $saldo;
                }
                $sub->saldo = $headerSaldo;

                $subsaldo = $this->cekSaldo($sub->id,$sub->saldo_normal, $year);
                $sub->saldo = $headerSaldo + $subsaldo;
            }

            $output[$nama] = $dd;
        }

        foreach ($output['pendapatan'] as $key => $pendapatan) {
            if($pendapatan->saldo_normal == 'DEBIT'){
                $totalPendapatan -= $pendapatan->saldo;
            }else{
                $totalPendapatan += $pendapatan->saldo;
            }
        }
        $output['pendapatan'][] = [
            'nama' => 'TOTAL PENDAPATAN',
            'align' => 1,
            'saldo' => $totalPendapatan
        ];

        foreach ($output['beban'] as $key => $beban) {
            if($beban->saldo_normal == 'DEBIT'){
                $totalBeban += $beban->saldo;
            }else{
                $totalBeban -= $beban->saldo;
            }
        }
        $output['beban'][] = [
            'nama' => 'TOTAL BEBAN',
            'align' => 1,
            'saldo' => $totalBeban
        ];

        return [
            'nama' => 'LABA BERJALAN',
            'saldo' =>  $totalPendapatan - $totalBeban
           ];
    }


    function cekSaldo($id, $sifat, $year){
        
        $dateawal = date($year.'-01-01 00:00:01');
        $dateakhir = date($year.'-12-31 23:59:59');

        $saldo = 0;
        $data = DB::table('master_jurnal')
        ->where('master_akun_id','=',$id)    
        ->where('master_jurnal.deleted_at')
        ->where('master_jurnal.created_at','>',$dateawal)    
        ->where('master_jurnal.created_at','<',$dateakhir)  
        ->get();

        if($sifat == 'DEBIT'){
            foreach ($data as $key => $value) {
                if($value->jenis === "DEBIT"){
                    $saldo += $value->nominal;
                }else{
                    $saldo -= $value->nominal;
                }
            }
        }else{
            foreach ($data as $key => $value) {
                if($value->jenis === "KREDIT"){
                    $saldo += $value->nominal;
                }else{
                    $saldo -= $value->nominal;
                }
            }
        }

        return $saldo;
    }
}
