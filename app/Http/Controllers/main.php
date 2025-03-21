<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Client\Pool;
use Inertia\Inertia;
use App\Models\tb_admision;
use App\Models\tb_maestria;
use App\Models\tb_equivalencia;
use App\Models\tb_nuevo_ingreso;
use App\Models\tb_re_ingreso;
use App\Models\tb_indicador_equivalencia;
use App\Models\tb_indicador_titulados;
use App\Models\tb_transporte_lugares;
use App\Models\tb_transporte_solicitudes_seleccionados;
use App\Models\tb_egresados;
use App\Models\tb_egresados_totales;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;
use Exception;

class main extends Controller
{
   private function getAuthToken($username, $password) {
      // URL del endpoint para obtener el token
      $url = "https://siiapi.upq.edu.mx:8000/token";
  
      // Datos que se enviarán en la solicitud POST
      $data = [
          'username' => $username,
          'password' => $password
      ];
  
      // Inicializar cURL
      $ch = curl_init($url);
  
      // Configurar opciones de cURL
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
          'Content-Type: application/x-www-form-urlencoded'
      ]);
  
      // Ejecutar la solicitud y obtener la respuesta
      $response = curl_exec($ch);
  
      // Verificar si hubo algún error
      if (curl_errno($ch)) {
          throw new Exception('Error en la solicitud cURL: ' . curl_error($ch));
      }
  
      // Cerrar la conexión cURL
      curl_close($ch);
  
      // Decodificar la respuesta JSON
      $responseData = json_decode($response, true);
  
      // Verificar si se obtuvo el token
      if (isset($responseData['access_token'])) {
          return $responseData['access_token'];
      } else {
          throw new Exception('No se pudo obtener el token de autenticación');
      }
  }
   
   

   private function generarTokenCSRF()
   {
      // Crear una instancia de GuzzleHttp Client
      $client = new Client();
      try {
         // Hacer la solicitud GET al endpoint de FastAPI que genera el token CSRF
         $response = $client->get('https://siiapi.upq.edu.mx:8000/get-csrf-token');

         // Decodificar la respuesta JSON
         $data = json_decode($response->getBody(), true);

         // Retornar el valor del token CSRF
         return $data['csrf_token'];
      } catch (Exception $e) {
         // Manejar errores (por ejemplo, si la API no está disponible)
         throw new Exception("Error al generar el token CSRF: " . $e->getMessage());
      }
   }

   public function testRedis()
   {

      $csrfToken = $this->generarTokenCSRF();
      \Log::info("Token generado: " . $csrfToken);
   }
   public function ingreso()
   {
       try {
           // Obtener el token de autenticación
           $token = $this->getAuthToken('admin','adminpassword');
   
           // 1. Concurrent requests para APIs externas con el token en los headers
           $responses = Http::pool(function (Pool $pool) use ($token) {
               return [
                   'ingresos' => $pool->withHeaders([
                       'Authorization' => 'Bearer ' . $token,
                   ])->get('https://siiapi.upq.edu.mx:8000/ingresos'),
   
                   'equivalencias' => $pool->withHeaders([
                       'Authorization' => 'Bearer ' . $token,
                   ])->get('https://siiapi.upq.edu.mx:8000/equivalencias'),
   
                   'maestrias' => $pool->withHeaders([
                       'Authorization' => 'Bearer ' . $token,
                   ])->get('https://siiapi.upq.edu.mx:8000/maestrias'),
   
                   'nuevosIngresos' => $pool->withHeaders([
                       'Authorization' => 'Bearer ' . $token,
                   ])->get('https://siiapi.upq.edu.mx:8000/nuevosIngresos'),
               ];
           });
   
           // 2. Procesar respuestas de APIs externas
           $ingresos = $responses[0]->successful() ? $responses[0]->json() : [];
           $equivalencias = $responses[1]->successful() ? $responses[1]->json() : [];
           $maestrias = $responses[2]->successful() ? $responses[2]->json() : [];
           $ningresos = $responses[3]->successful() ? $responses[3]->json() : [];
   
           // 3. Carga de datos adicionales desde la base de datos
           $reingresos = tb_re_ingreso::all();
   
           return Inertia::render('menusComponentes/Ingreso/TabMenu', [
               'maestrias' => $maestrias,
               'ingresos' => $ingresos,
               'equivalencias' => $equivalencias,
               'ningresos' => $ningresos,
               'reingresos' => $reingresos,
           ]);
   
       } catch (Exception $e) {
           return Inertia::render('menusComponentes/Ingreso/TabMenu', [
               'error' => $e->getMessage(),
           ]);
       }
   }

   public function bajas()
   {
      return Inertia::render('menusComponentes/Bajas');
   }

   public function matricula()
   {
      return Inertia::render('menusComponentes/Matricula');
   }

   public function egresados()
   {
       try {
           // Obtener el token de autenticación
           $token = $this->getAuthToken('admin', 'adminpassword');
   
           // 1. Concurrent requests para APIs externas con el token en los headers
           $responses = Http::pool(function (Pool $pool) use ($token) {
               return [
                   'egresados' => $pool->withHeaders([
                       'Authorization' => 'Bearer ' . $token,
                   ])->get('https://siiapi.upq.edu.mx:8000/egresados'),
   
                   'egresadostotales' => $pool->withHeaders([
                       'Authorization' => 'Bearer ' . $token,
                   ])->get('https://siiapi.upq.edu.mx:8000/egresadostotales'),
               ];
           });
   
           // 2. Procesar respuestas de APIs externas
           $egresados = optional($responses[0])->successful() ? $responses[0]->json() : [];
           $egresadostotales = optional($responses[1])->successful() ? $responses[1]->json() : [];
   
           // 3. Carga de datos adicionales desde la base de datos
           $egresados_totales = tb_egresados_totales::all();
   
           return Inertia::render('menusComponentes/Egresados/TabMenuEgre', [
               'egresados' => $egresados,
               'totales' => $egresadostotales,
               'egresados_totales' => $egresados_totales,
           ]);
   
       } catch (Exception $e) {
           return Inertia::render('menusComponentes/Egresados/TabMenuEgre', [
               'error' => $e->getMessage(),
           ]);
       }
   }
   #holamundo .D

   /* public function titulados()
   Comeno esta vieja funcion de titulados no se porque, supongo que por si acaso
   {
      try {
         // Obtener el token de autenticación
         $token = $this->getAuthToken();

         // Generar el token CSRF
         $csrfToken = $this->generarTokenCSRF();

         // Realizar solicitudes a los endpoints protegidos
         $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-CSRF-Token' => $csrfToken,
         ])->get('http://127.0.0.1:8000/titulados');

         if ($response->successful()) {
            $titulados = $response->json();
         } else {
            $titulados = [];
         }

         return Inertia::render('menusComponentes/Titulo/TabMenuTitu', [
            'titulados' => $titulados,
         ]);
      } catch (Exception $e) {
         return Inertia::render('menusComponentes/Titulo/TabMenuTitu', [
            'error' => $e->getMessage(),
         ]);
      }
   } */

   public function titulados()
{
    try {
        // 1. Obtener el token de autenticación
        $token = $this->getAuthToken('admin', 'adminpassword');
        //dd($token);

        // 2. Concurrent requests para APIs externas
        $responses = Http::pool(function (Pool $pool) use ($token) {
            // Peticiones de la pestaña de titulados
            $pool->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get('https://siiapi.upq.edu.mx:8000/titulados');
        });

        // 3. Procesar las respuestas
        $titulados = $responses[0]->successful() ? $responses[0]->json() : [];

        return Inertia::render('menusComponentes/Titulo/TabMenuTitu', [
            'titulados' => $titulados,
        ]);
    } catch (Exception $e) {
        return Inertia::render('menusComponentes/Titulo/TabMenuTitu', [
            'error' => $e->getMessage(),
        ]);
    }
}

   public function becas()
   {
      return Inertia::render('menusComponentes/Becas');
   }

   /* public function transporte()
   Comento esta funcion por si acaso. Igual que la anterior.
   {
      try {
         // Obtener el token de autenticación
         $token = $this->getAuthToken();

         // Generar el token CSRF
         $csrfToken = $this->generarTokenCSRF();

         // Realizar solicitudes a los endpoints protegidos
         $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-CSRF-Token' => $csrfToken,
         ])->get('http://127.0.0.1:8000/transporte_solicitudes');

         if ($response->successful()) {
            $solicitudes = $response->json();
         } else {
            $solicitudes = [];
         }

         $response2 = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-CSRF-Token' => $csrfToken,
         ])->get('http://127.0.0.1:8000/rutas');

         if ($response2->successful()) {
            $rutas = $response2->json();
         } else {
            $rutas = [];
         }

         return Inertia::render('menusComponentes/Transporte/TabMenu', [
            'solicitudes' => $solicitudes,
            'rutas' => $rutas,
         ]);
      } catch (Exception $e) {
         return Inertia::render('menusComponentes/Transporte/TabMenu', [
            'error' => $e->getMessage(),
         ]);
      }
   } */

   public function transporte()
   {
       try {
           // 1. Obtener token de autenticación
           $token = $this->getAuthToken('admin', 'adminpassword');
   
           // 2. Concurrent requests con autenticación
           $responses = Http::pool(function (Pool $pool) use ($token) {
               return [
                   $pool->withHeaders([
                       'Authorization' => 'Bearer ' . $token,
                   ])->get('https://siiapi.upq.edu.mx:8000/transporte_solicitudes'),
                   
                   $pool->withHeaders([
                       'Authorization' => 'Bearer ' . $token,
                   ])->get('https://siiapi.upq.edu.mx:8000/rutas')
               ];
           });
   
           // 3. Procesar respuestas por índice numérico
           $solicitudes = isset($responses[0]) && $responses[0]->successful() 
                        ? $responses[0]->json() 
                        : [];
   
           $rutas = isset($responses[1]) && $responses[1]->successful() 
                  ? $responses[1]->json() 
                  : [];
   
           return Inertia::render('menusComponentes/Transporte/TabMenu', [
               'solicitudes' => $solicitudes,
               'rutas' => $rutas,
           ]);
   
       } catch (Exception $e) {
           return Inertia::render('menusComponentes/Transporte/TabMenu', [
               'error' => $e->getMessage(),
           ]);
       }
   }

   public function cambioDeCarrera()
   {
      return Inertia::render('menusComponentes/CambioDeCarrera');
   }

   public function equivalencia()
   {
      // traer de la tabla tb_admision todos los registros
      $equiva = tb_indicador_equivalencia::all();
      return Inertia::render('menusComponentes/Equivalencia/TabMenuEqui', ['equiva' => $equiva]);
   }

   public function importarDataExcelAdmisiones(Request $request)
   {
      $datosExcel = $request->input('datos');

      foreach ($datosExcel as $fila) {
         $admision = new tb_admision();
         $admision->carrera = $fila['carrera'];
         $admision->aspirantes = $fila['aspirantes'];
         $admision->examinados = $fila['examinados'];
         $admision->no_admitidos = $fila['no_admitidos'];
         $admision->periodo = $fila['periodo'];
         $admision->save();
      }

      return redirect()->route('usuario.ingreso');
   }



   // ruta para guardar una nueva admision del indicador ingreso en la admision
   function registrarAdmision(Request $request)
   {
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







   //  --------------------- TAB ADMISION -----------------------

   // ruta para editar una admision
   function editarAdmision(Request $request)
   {
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

   function eliminarAdmision(Request $request)
   {
      $id = $request->input('id');
      $admision = tb_admision::findOrFail($id);
      $admision->delete();
      return redirect()->route('usuario.ingreso');
   }

   function eliminarAdmisiones(Request $request)
   {
      $id = $request->id;
      $admision = tb_admision::whereIn('id', $id);
      $admision->delete();
      return redirect()->route('usuario.ingreso');
   }

   //  --------------------- FIN TAB ADMISION -----------------------

   // ---------------------- TAB TITULADOS --------------------------

   function registrarTitulacion(Request $request)
   {

      $generacion = $request->input('generacion');
      $carrera = $request->input('carrera');
      $total = $request->input('total');
      $cedula = $request->input('cedula');
      $cuatrimestre_egreso = $request->input('cuatrimestre_egreso');
      $fecha_titulacion = $request->input('fecha_titulacion');

      // crear un nuevo registro en la tabla tb_indicador_titulados
      $titulacion = new tb_indicador_titulados();
      $titulacion->carrera = $carrera;
      $titulacion->generacion = $generacion;
      $titulacion->total = $total;
      $titulacion->cedula = $cedula;
      $titulacion->cuatrimestre_egreso = $cuatrimestre_egreso;
      $titulacion->fecha_titulacion = $fecha_titulacion;
      $titulacion->save();

      // retornar a la vista ingreso
      return redirect()->route('usuario.titulados');

   }

   // ruta para editar una admision
   function editarTitulacion(Request $request)
   {
      // obtener los datos dle form y luego actualizar el registro
      $id = $request->input('id');
      $carrera = $request->input('carrera');
      $generacion = $request->input('generacion');
      $total = $request->input('total');
      $cedula = $request->input('cedula');
      $cuatrimestre_egreso = $request->input('cuatrimestre_egreso');
      $fecha_titulacion = $request->input('fecha_titulacion');

      // actualizar el registro
      $titulacion = tb_indicador_titulados::find($id);
      $titulacion->carrera = $carrera;
      $titulacion->generacion = $generacion;
      $titulacion->total = $total;
      $titulacion->cedula = $cedula;
      $titulacion->cuatrimestre_egreso = $cuatrimestre_egreso;
      $titulacion->fecha_titulacion = $fecha_titulacion;
      $titulacion->save();

      // retornar a la vista ingreso
      return redirect()->route('usuario.titulados');
   }

   function eliminarTitulacion(Request $request)
   {
      $id = $request->input('id');
      $titulacion = tb_indicador_titulados::findOrFail($id);
      $titulacion->delete();
      return redirect()->route('usuario.titulados');
   }

   function eliminarTitulaciones(Request $request)
   {
      $id = $request->id;
      $titulacion = tb_indicador_titulados::whereIn('id', $id);
      $titulacion->delete();
      return redirect()->route('usuario.titulados');
   }

   // ---------------------- FIN TAB TITULADOS ----------------------

   //  --------------------- TAB NUEVO INGRESO -----------------------

   function registrarNIngreso(Request $request)
   {
      $carrera = $request->input('carrera');
      $total_ingresos = $request->input('totalIngresos');
      $sexo = $request->input('sexo');
      $generacion = $request->input('generacion');
      $admitidos = $request->input('admitidos');
      $inscritos = $request->input('inscritos');
      $proceso = $request->input('procesos');
      $periodo = $request->input('periodos');

      // crear un nuevo registro en la tabla tb_nuevo_ingreso
      $nuevo_ingreso = new tb_nuevo_ingreso();
      $nuevo_ingreso->carrera = $carrera;
      $nuevo_ingreso->total_ingresos = $total_ingresos;
      $nuevo_ingreso->sexo = $sexo;
      $nuevo_ingreso->generacion = $generacion;
      $nuevo_ingreso->admitidos = $admitidos;
      $nuevo_ingreso->inscritos = $inscritos;
      $nuevo_ingreso->proceso = $proceso;
      $nuevo_ingreso->periodo = $periodo;
      $nuevo_ingreso->save();


      // retornar a la vista ingres-o
      return redirect()->route('usuario.ingreso');
   }

   function eliminarNIngresos(Request $request)
   {
      $id = $request->id;
      $nuevo_ingreso = tb_nuevo_ingreso::whereIn('id', $id);
      $nuevo_ingreso->delete();
      return redirect()->route('usuario.ingreso');
   }

   function eliminarNIngreso(Request $request)
   {
      $id = $request->input('id');
      $nuevo_ingreso = tb_nuevo_ingreso::findOrFail($id);
      $nuevo_ingreso->delete();
      return redirect()->route('usuario.ingreso');
   }

   function editarNIngreso(Request $request)
   {
      // recibir los datos del form
      $id = $request->input('id');
      $carrera = $request->input('carrera');
      $total_ingresos = $request->input('totalIngresos');
      $sexo = $request->input('sexo');
      $generacion = $request->input('generacion');
      $admitidos = $request->input('admitidos');
      $inscritos = $request->input('inscritos');
      $proceso = $request->input('procesos');
      $periodo = $request->input('periodos');

      // actualizar el registro
      $nuevo_ingreso = tb_nuevo_ingreso::find($id);
      $nuevo_ingreso->carrera = $carrera;
      $nuevo_ingreso->total_ingresos = $total_ingresos;
      $nuevo_ingreso->sexo = $sexo;
      $nuevo_ingreso->generacion = $generacion;
      $nuevo_ingreso->admitidos = $admitidos;
      $nuevo_ingreso->inscritos = $inscritos;
      $nuevo_ingreso->proceso = $proceso;
      $nuevo_ingreso->periodo = $periodo;
      $nuevo_ingreso->save();

      // retornar a la vista ingreso
      return redirect()->route('usuario.ingreso');
   }

   //  --------------------- TAB NUEVO RE INGRESO -----------------------

   function registrarRIngreso(Request $request)
   {
      $carrera = $request->input('carrera');
      $cuatrimestre = $request->input('cuatrimestre');
      $generacion = $request->input('generacion');
      $tipo_baja = $request->input('bajas');
      $periodo = $request->input('periodo');

      // crear un nuevo registro en la tabla tb_re_ingreso
      $re_ingreso = new tb_re_ingreso();
      $re_ingreso->carrera = $carrera;
      $re_ingreso->cuatrimestre = $cuatrimestre;
      $re_ingreso->generacion = $generacion;
      $re_ingreso->tipo_baja = $tipo_baja;
      $re_ingreso->periodo = $periodo;
      $re_ingreso->save();

      // retornar a la vista ingreso
      return redirect()->route('usuario.ingreso');

   }

   function editarRIngresos(Request $request)
   {
      // recibir los datos del form
      $id = $request->input('id');
      $carrera = $request->input('carrera');
      $cuatrimestre = $request->input('cuatrimestre');
      $generacion = $request->input('generacion');
      $tipo_baja = $request->input('bajas');
      $periodo = $request->input('periodo');

      // actualizar el registro
      $re_ingreso = tb_re_ingreso::find($id);
      $re_ingreso->carrera = $carrera;
      $re_ingreso->cuatrimestre = $cuatrimestre;
      $re_ingreso->generacion = $generacion;
      $re_ingreso->tipo_baja = $tipo_baja;
      $re_ingreso->periodo = $periodo;
      $re_ingreso->save();

      // retornar a la vista ingreso
      return redirect()->route('usuario.ingreso');
   }

   function eliminarRIngreso(Request $request)
   {
      $id = $request->input('id');
      $re_ingreso = tb_re_ingreso::findOrFail($id);
      $re_ingreso->delete();
      return redirect()->route('usuario.ingreso');
   }

   function eliminarRIngresos(Request $request)
   {
      $id = $request->id;
      $re_ingreso = tb_re_ingreso::whereIn('id', $id);
      $re_ingreso->delete();
      return redirect()->route('usuario.ingreso');
   }


   //  --------------------- TAB NUEVO EQUIVALENCIA -----------------------
   // ruta para guardar una nueva equivalencia del indicador ingreso en la equivalencia
   function registrarEquivalencia(Request $request)
   {
      $carrera = $request->input('carreras');
      $aspirantes = $request->input('aspirantes');
      $examinados = $request->input('examinados');
      $no_admitidos = $request->input('noAdmitidos');
      $periodo = $request->input('periodos');

      // crear un nuevo registro en la tabla tb_equivalencia
      $admision = new tb_equivalencia();
      $admision->carrera = $carrera;
      $admision->aspirantes = $aspirantes;
      $admision->examinados = $examinados;
      $admision->no_admitidos = $no_admitidos;
      $admision->periodo = $periodo;
      $admision->save();

      // retornar a la vista ingreso
      return redirect()->route('usuario.ingreso');

   }

   // ruta para editar una equivalencia
   function editarEquivalencia(Request $request)
   {
      // obtener los datos dle form y luego actualizar el registro
      $id = $request->input('id');
      $carrera = $request->input('carrera');
      $aspirantes = $request->input('aspirantes');
      $examinados = $request->input('examinados');
      $no_admitidos = $request->input('no_admitidos');
      $periodo = $request->input('periodo');

      // actualizar el registro
      $admision = tb_equivalencia::find($id);
      $admision->carrera = $carrera;
      $admision->aspirantes = $aspirantes;
      $admision->examinados = $examinados;
      $admision->no_admitidos = $no_admitidos;
      $admision->periodo = $periodo;
      $admision->save();

      // retornar a la vista ingreso
      return redirect()->route('usuario.ingreso');
   }

   function eliminarEquivalencia(Request $request)
   {
      $id = $request->input('id');
      $equivalencia = tb_equivalencia::findOrFail($id);
      $equivalencia->delete();
      return redirect()->route('usuario.ingreso');
   }

   function eliminarEquivalencias(Request $request)
   {
      $id = $request->id;
      $equivalencia = tb_equivalencia::whereIn('id', $id);
      $equivalencia->delete();
      return redirect()->route('usuario.ingreso');
   }

   //  --------------------- TAB NUEVO MAESTRIAS -----------------------
   // ruta para guardar una nueva MAESTRIA del indicador ingreso en la maestria

   function registrarMaestria(Request $request)
   {
      $carrera = $request->input('carreras');
      $aspirantes = $request->input('aspirantes');
      $examinados = $request->input('examinados');
      $no_admitidos = $request->input('noAdmitidos');
      $periodo = $request->input('periodos');

      // crear un nuevo registro en la tabla tb_equivalencia
      $admision = new tb_maestria();
      $admision->carrera = $carrera;
      $admision->aspirantes = $aspirantes;
      $admision->examinados = $examinados;
      $admision->no_admitidos = $no_admitidos;
      $admision->periodo = $periodo;
      $admision->save();

      // retornar a la vista ingreso
      return redirect()->route('usuario.ingreso');

   }

   // ruta para editar una maestria
   function editarMaestria(Request $request)
   {
      // obtener los datos dle form y luego actualizar el registro
      $id = $request->input('id');
      $carrera = $request->input('carrera');
      $aspirantes = $request->input('aspirantes');
      $examinados = $request->input('examinados');
      $no_admitidos = $request->input('no_admitidos');
      $periodo = $request->input('periodo');

      // actualizar el registro
      $admision = tb_maestria::find($id);
      $admision->carrera = $carrera;
      $admision->aspirantes = $aspirantes;
      $admision->examinados = $examinados;
      $admision->no_admitidos = $no_admitidos;
      $admision->periodo = $periodo;
      $admision->save();

      // retornar a la vista ingreso
      return redirect()->route('usuario.ingreso');
   }

   function eliminarMaestria(Request $request)
   {
      $id = $request->input('id');
      $maestria = tb_maestria::findOrFail($id);
      $maestria->delete();
      return redirect()->route('usuario.ingreso');
   }

   function eliminarMaestrias(Request $request)
   {
      $id = $request->id;
      $maestria = tb_maestria::whereIn('id', $id);
      $maestria->delete();
      return redirect()->route('usuario.ingreso');
   }

   //  --------------------- Fin Maestrias -----------------------//

   //---------------TRANSPORTE----------------//

   function registrarTranspSolicitudes(Request $request)
   {
      $carrera = $request->input('carrera');
      $ruta = $request->input('ruta');
      $solicitudes = $request->input('solicitudes');
      $hombres = $request->input('hombres');
      $mujeres = $request->input('mujeres');
      $seleccionados = $hombres + $mujeres;
      $cuatrimestre = $request->input('cuatrimestre');
      $turno = $request->input('turno');


      // crear un nuevo registro en la tabla tb_transporte_solicit...
      $transpSolicit = new tb_transporte_solicitudes_seleccionados();
      $transpSolicit->solicitudes = $solicitudes;
      $transpSolicit->seleccionados = $seleccionados;
      $transpSolicit->hombres = $hombres;
      $transpSolicit->mujeres = $mujeres;
      $transpSolicit->carrera = $carrera;
      $transpSolicit->ruta = $ruta;
      $transpSolicit->cuatrimestre = $cuatrimestre;
      $transpSolicit->turno = $turno;
      $transpSolicit->save();



      // retornar a la vista ingres-o
      return redirect()->route('usuario.transporte');
   }
   function eliminarTranspSolicitudes(Request $request)
   {
      $id = $request->id;
      $nuevo_ingreso = tb_transporte_solicitudes_seleccionados::whereIn('id', $id);
      $nuevo_ingreso->delete();
      return redirect()->route('usuario.transporte');
   }

   function eliminarTranspSolicitud(Request $request)
   {
      $id = $request->input('id');
      $nuevo_ingreso = tb_transporte_solicitudes_seleccionados::findOrFail($id);
      $nuevo_ingreso->delete();
      return redirect()->route('usuario.transporte');
   }

   function eliminarTranspRuta(Request $request)
   {
      $id = $request->input('id');
      $nuevo_ingreso = tb_transporte_lugares::findOrFail($id);
      $nuevo_ingreso->delete();
      return redirect()->route('usuario.transporte');
   }

   function registrarTranspRutas(Request $request)
   {
      $ruta = $request->input('ruta');
      $lugares = $request->input('lugares_disp');
      $pagados = $request->input('pagados');
      $cuatrimestre = $request->input('cuatrimestre');
      $turno = $request->input('turno');


      // crear un nuevo registro en la tabla tb_transporte_solicit...
      $transpRutas = new tb_transporte_lugares();
      $transpRutas->ruta = $ruta;
      $transpRutas->cuatrimestre = $cuatrimestre;
      $transpRutas->turno = $turno;
      $transpRutas->lugares_disp = $lugares;
      $transpRutas->pagados = $pagados;
      $transpRutas->save();



      // retornar a la vista ingres-o
      return redirect()->route('usuario.transporte');
   }
   function eliminarTranspRutas(Request $request)
   {
      $id = $request->id;
      $nuevo_ingreso = tb_transporte_lugares::whereIn('id', $id);
      $nuevo_ingreso->delete();
      return redirect()->route('usuario.transporte');
   }

   function editarTranspSolicitudes(Request $request)
   {
      // recibir los datos del form
      $id = $request->input('id');
      $carrera = $request->input('carrera');
      $ruta = $request->input('ruta');
      $solicitudes = $request->input('solicitudes');
      $hombres = $request->input('hombres');
      $mujeres = $request->input('mujeres');
      $seleccionados = $hombres + $mujeres;
      $cuatrimestre = $request->input('cuatrimestre');
      $turno = $request->input('turno');

      // actualizar el registro
      $transpSolicit = tb_transporte_solicitudes_seleccionados::find($id);
      $transpSolicit->solicitudes = $solicitudes;
      $transpSolicit->hombres = $hombres;
      $transpSolicit->mujeres = $mujeres;
      $transpSolicit->seleccionados = $seleccionados;
      $transpSolicit->carrera = $carrera;
      $transpSolicit->ruta = $ruta;
      $transpSolicit->cuatrimestre = $cuatrimestre;
      $transpSolicit->turno = $turno;
      $transpSolicit->save();

      // retornar a la vista ingreso
      return redirect()->route('usuario.transporte');
   }
   function editarTranspRutas(Request $request)
   {
      // recibir los datos del form
      $id = $request->input('id');
      $ruta = $request->input('ruta');
      $lugares = $request->input('lugares_disp');
      $pagados = $request->input('pagados');
      $cuatrimestre = $request->input('cuatrimestre');
      $turno = $request->input('turno');

      // actualizar el registro

      $transpRutas = tb_transporte_lugares::find($id);
      $transpRutas->ruta = $ruta;
      $transpRutas->cuatrimestre = $cuatrimestre;
      $transpRutas->turno = $turno;
      $transpRutas->lugares_disp = $lugares;
      $transpRutas->pagados = $pagados;
      $transpRutas->save();

      // retornar a la vista ingreso
      return redirect()->route('usuario.transporte');

      //-------------------------FIN TRANSPORTE-----------------------------

   }
   function eliminarEquivalencias2(Request $request)
   {
      $id = $request->id;
      $equiva = tb_indicador_equivalencia::whereIn('id', $id);
      $equiva->delete();
      return redirect()->route('usuario.equivalencia');
   }


   // ------------------------------ INICIO EGRESADOS ----------------------------

   // ruta para guardar un nuevo egreso del indicador egresados
   function registrarEgresados(Request $request)
   {
      $carrera = $request->input('carrera');
      $generacion = $request->input('generacion');
      $año_egreso = $request->input('año_egreso');
      $cuatrimestre = $request->input('cuatrimestre');
      $hombres = $request->input('hombres');
      $mujeres = $request->input('mujeres');
      $egresados = $hombres + $mujeres;
      // crear un nuevo registro en la tabla egresados
      $egresado = new tb_egresados();
      $egresado->carrera = $carrera;
      $egresado->generacion = $generacion;
      $egresado->egresados = $egresados;
      $egresado->año_egreso = $año_egreso;
      $egresado->cuatrimestre = $cuatrimestre;
      $egresado->hombres = $hombres;
      $egresado->mujeres = $mujeres;
      $egresado->save();

      // retornar a la vista ingreso
      return redirect()->route('usuario.egresados');
   }

   function eliminarEgresados(Request $request)
   {
      $id = $request->id;
      $egresado = tb_egresados::whereIn('id', $id);
      $egresado->delete();
      return redirect()->route('usuario.egresados');
   }

   function eliminarEgreso(Request $request)
   {
      $id = $request->input('id');
      $egresado = tb_egresados::findOrFail($id);
      $egresado->delete();
      return redirect()->route('usuario.egresados');
   }

   function editarEgreso(Request $request)
   {
      $id = $request->input('id');
      $carrera = $request->input('carrera');
      $generacion = $request->input('generacion');
      $hombres = $request->input('hombres');
      $mujeres = $request->input('mujeres');
      $egresados = $hombres + $mujeres;
      $año_egreso = $request->input('año_egreso');
      $cuatrimestre = $request->input('cuatrimestre');

      // actualizar el registro
      $egresado = tb_egresados::find($id);
      $egresado->carrera = $carrera;
      $egresado->generacion = $generacion;
      $egresado->egresados = $egresados;
      $egresado->año_egreso = $año_egreso;
      $egresado->cuatrimestre = $cuatrimestre;
      $egresado->hombres = $hombres;
      $egresado->mujeres = $mujeres;
      $egresado->save();

      // retornar a la vista ingreso
      return redirect()->route('usuario.egresados');
   }

   //--------------------------------TOTALES------------------------------------

   function registrarEgresadosTotales(Request $request)
   {
      $carrera = $request->input('carrera');
      $año_egreso = $request->input('anio');
      $cuatrimestre = $request->input('cuatrimestre');
      $hombres = $request->input('hombres');
      $mujeres = $request->input('mujeres');
      $egresados = $hombres + $mujeres;
      // crear un nuevo registro en la tabla egresados
      $egresadoTotal = new tb_egresados_totales();
      $egresadoTotal->carrera = $carrera;
      $egresadoTotal->egresados = $egresados;
      $egresadoTotal->anio = $año_egreso;
      $egresadoTotal->periodo = $cuatrimestre;
      $egresadoTotal->hombres = $hombres;
      $egresadoTotal->mujeres = $mujeres;
      $egresadoTotal->save();
      return redirect()->route('usuario.egresados');
   }

   function eliminarEgresoTotales(Request $request)
   {
      $id = $request->input('id');
      $egresadoTotal = tb_egresados_totales::findOrFail($id);
      $egresadoTotal->delete();
      return redirect()->route('usuario.egresados');
   }

   function eliminarEgresosTotales(Request $request)
   {
      $id = $request->id;
      $egresadoTotal = tb_egresados_totales::whereIn('id', $id);
      $egresadoTotal->delete();
      return redirect()->route('usuario.egresados');
   }

   function editarEgresoTotales(Request $request)
   {
      $id = $request->input('id');
      $carrera = $request->input('carrera');
      $año_egreso = $request->input('anio');
      $cuatrimestre = $request->input('cuatrimestre');
      $hombres = $request->input('hombres');
      $mujeres = $request->input('mujeres');
      $egresados = $hombres + $mujeres;

      // actualizar el registro
      $egresadoTotal = tb_egresados_totales::find($id);
      $egresadoTotal->carrera = $carrera;
      $egresadoTotal->egresados = $egresados;
      $egresadoTotal->anio = $año_egreso;
      $egresadoTotal->periodo = $cuatrimestre;
      $egresadoTotal->hombres = $hombres;
      $egresadoTotal->mujeres = $mujeres;
      $egresadoTotal->save();

      // retornar a la vista ingreso
      return redirect()->route('usuario.egresados');
   }

   // ------------------------------ FIN EGRESADOS ----------------------------


}