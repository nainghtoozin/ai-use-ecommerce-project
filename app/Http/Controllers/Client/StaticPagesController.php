<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class StaticPagesController extends Controller
{
    public function about()
    {
        return Inertia::render('Client/Pages/About');
    }

    public function contact()
    {
        return Inertia::render('Client/Pages/Contact');
    }

    public function faq()
    {
        return Inertia::render('Client/Pages/Faq');
    }

    public function privacy()
    {
        return Inertia::render('Client/Pages/Privacy');
    }

    public function terms()
    {
        return Inertia::render('Client/Pages/Terms');
    }
}
