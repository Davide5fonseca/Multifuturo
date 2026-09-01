<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Support\Modules;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A página de escolha: os módulos a que a pessoa tem acesso. É aqui que se
 * aterra depois de entrar, sempre — mesmo com um só módulo — para toda a
 * gente saber onde está e de onde parte.
 */
class PortalController extends Controller
{
    public function index(Request $request): View
    {
        return view('portal.index', ['modules' => Modules::forUser($request->user())]);
    }
}
