<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

final class InstitutionalSiteController extends Controller
{
    public function home(): View
    {
        return $this->page('home');
    }

    public function features(): View
    {
        return $this->page('features');
    }

    public function support(): View
    {
        return $this->page('support');
    }

    public function privacy(): View
    {
        return $this->page('privacy');
    }

    public function terms(): View
    {
        return $this->page('terms');
    }

    private function page(string $view): View
    {
        return view("institutional.{$view}", [
            'productName' => config('game.name'),
            'locale' => app()->getLocale(),
        ]);
    }
}
