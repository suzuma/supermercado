<?php
/*
    autor: Noe Cazarez Camargo
    fecha: 2019-06-24
    descripcion: 
*/
namespace App\Helpers;

class ResponseHelper {
	public $result      = null;
	public $response    = false;
	public $message     = 'Ocurrio un error inesperado.';
	public $href        = null;
	public $function    = null;
	public $filter      = null;
    public $validations = [];
	
	public function setResponse($response, $m = '') {
		$this->response = $response;
		$this->message = $m;

		if (!$response && $m == '') {
            $this->message = 'Ocurrio un error inesperado';
        }

        return $this;
	}

    public function setErrors($error) {
		$this->response = false;
		$this->validations = $error;
        $this->message = 'Error de validación';

        return $this;
    }
}