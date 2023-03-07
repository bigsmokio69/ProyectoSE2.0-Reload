<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\tb_admision;
use App\Models\tb_maestria;
use App\Models\tb_equivalencia;
use App\Models\tb_nuevo_ingreso;
use App\Models\tb_re_ingreso;
class main extends Controller
{
   public function ingreso()
   {
      // traer de la tabla tb_admision todos los registros
      $ingresos = tb_admision::all();
      $maestrias = tb_maestria::all();
      $equivalencias = tb_equivalencia::all();
      $ningresos = tb_nuevo_ingreso::all();
      $reingresos = tb_re_ingreso::all();
      // retornar con Inertia a menusComponentes/TabMenu y pasarle los registros
      return Inertia::render('menusComponentes/Ingreso/TabMenu', ['maestrias' => $maestrias,'ingresos' => $ingresos, 'equivalencias' => $equivalencias, 'ningresos' => $ningresos, 'reingresos' => $reingresos]);
   }

   public function bajas() {
      return Inertia::render('menusComponentes/Bajas');
   }

   public function matricula(){
      return Inertia::render('menusComponentes/Matricula');
   }

   public function egresados(){
      return Inertia::render('menusComponentes/Egresados');
   }

   public function titulados(){
      return Inertia::render('menusComponentes/Titulados');
   }

   public function becas(){
      return Inertia::render('menusComponentes/Becas');
   }

   public function transporte(){
      return Inertia::render('menusComponentes/Transporte');
   }

   public function cambioDeCarrera(){
      return Inertia::render('menusComponentes/CambioDeCarrera');
   }

   public function seguroFacultativo(){
      return Inertia::render('menusComponentes/SeguroFacultativo');
   }


   // ruta para guardar una nueva admision del indicador ingreso en la admision
   function registrarAdmision(Request $request) {
      $carrera = $request->input('carreras');
      $aspirantes = $request->input('aspirantes');
      $examinados = $request->input('examinados');
      $no_admitidos = $request->input('noAdmitidos');
      $periodo = $request->input('periodos');

      // crear un nuevo registro en la tabla tb_admision
      $admision = new tb_admision();
      $admision->carrera = $carrera;
      $admision->aspirantes = $aspirantes;
      $admision->examinados = $examinados;
      $admision->no_admitidos = $no_admitidos;
      $admision->periodo = $periodo;
      $admision->save();

      // retornar a la vista ingreso
      return redirect()->route('usuario.ingreso');
   
  }

  // ruta para editar una admision
  function editarAdmision(Request $request) {
   // obtener los datos dle form y luego actualizar el registro
   $id = $request->input('id');
   $carrera = $request->input('carrera');
   $aspirantes = $request->input('aspirantes');
   $examinados = $request->input('examinados');
   $no_admitidos = $request->input('no_admitidos');
   $periodo = $request->input('periodo');

   // actualizar el registro
   $admision = tb_admision::find($id);
   $admision->carrera = $carrera;
   $admision->aspirantes = $aspirantes;
   $admision->examinados = $examinados;
   $admision->no_admitidos = $no_admitidos;
   $admision->periodo = $periodo;
   $admision->save();

   // retornar a la vista ingreso
   return redirect()->route('usuario.ingreso');
  }

  function eliminarAdmision(Request $request) {
   $id = $request->input('id');
   $admision = tb_admision::findOrFail($id);
   $admision->delete(); 
   return redirect()->route('usuario.ingreso');
  }

  function eliminarAdmisiones(Request $request) {
   $id = $request->id;
   $admision = tb_admision::whereIn('id', $id);
   $admision->delete();
   return redirect()->route('usuario.ingreso');
  }
}
