<?php
/*
    autor: Noe Cazarez Camargo
    fecha: 2019-06-24
    descripcion: CLASE RESPONSABLE DE MANEJAR LAS VISTAS PARA LA AUTENTIFICACION
*/
namespace App\Controllers;

use Core\{Auth, Controller, Csrf, Log};
use App\Helpers\{UrlHelper, ResponseHelper};
use App\Repositories\{UsuarioRepository};

class AuthController extends Controller {
    private $usuarioRepo;

    public function __construct() {
        // Si ya está logueado, no tiene caso mostrar el login
        if (Auth::isLoggedIn()) {
            UrlHelper::redirect('home');
        }

        parent::__construct();
        $this->usuarioRepo = new UsuarioRepository();

    }

    public function getIndex() {
        return $this->render('auth/login.twig', [
            'title' => 'Autentificación',
            'menu'  => false,
            'base_url' => UrlHelper::base('')
        ]);

    }

    /*
     * router de Phroute: el método en tu código original es postsignin (todo junto y minúscula) — Phroute mapea
     * automáticamente POST /auth/signin al método postSignin (con S mayúscula).
     * */
    public function postSignin()
    {
        Csrf::validateOrFail();

        $rh = new ResponseHelper;

        if (empty($_POST['email']) || empty($_POST['password'])) {
            $rh->setResponse(false, 'Correo y contraseña son requeridos');
            echo json_encode($rh);
            return;
        }

        $rh = $this->usuarioRepo->autenticar(
            trim($_POST['email']),
            $_POST['password']
        );

        if ($rh->response) {
            $rh->href = 'home';
        }

        // Sin header() — solo el echo
        echo json_encode($rh);
    }

    public function getSignout()
    {
        Auth::destroy();
        UrlHelper::redirect('auth');
    }
}