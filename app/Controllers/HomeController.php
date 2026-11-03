<?php

namespace App\Controllers;

use App\Core\Controller;

class HomeController extends Controller
{
    public function index(): void
    {
        $this->view('home', [
            'titulo' => 'SGECE',
            'mensagem' => 'Sistema de Gestão e Emissão de Certificados Escolares'
        ]);
    }
}