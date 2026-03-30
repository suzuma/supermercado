<?php
declare(strict_types=1);

namespace App\Controllers;

use Core\{Auth, Controller, Csrf, Log};
use App\Helpers\{UrlHelper, ResponseHelper};
use App\Repositories\{UsuarioRepository};

class AuthController extends Controller {
    private UsuarioRepository $usuarioRepo;

    public function __construct() {
        if (Auth::isLoggedIn()) {
            UrlHelper::redirect('home');
        }

        parent::__construct();
        $this->usuarioRepo = new UsuarioRepository();
    }

    public function getIndex(): string {
        return $this->render('auth/login.twig', [
            'title'    => 'Autentificación',
            'menu'     => false,
            'base_url' => UrlHelper::base('')
        ]);
    }

    public function postSignin(): void
    {
        Csrf::validateOrFail();

        $rh = new ResponseHelper();

        if (empty($_POST['email']) || empty($_POST['password'])) {
            echo json_encode($rh->setResponse(false, 'Correo y contraseña son requeridos'));
            return;
        }

        $rh = $this->usuarioRepo->autenticar(
            trim($_POST['email']),
            $_POST['password']
        );

        if ($rh->response) {
            $rh->href = 'home';
        }

        echo json_encode($rh);
    }

    public function getSignout(): void
    {
        Auth::destroy();
        UrlHelper::redirect('auth');
    }
}
