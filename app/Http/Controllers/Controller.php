<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Response;
use App\Core\Session;
use App\Core\View;

abstract class Controller
{
    protected function view(string $view, array $data = []): Response
    {
        return Response::html(View::render($view, $data));
    }

    protected function page(string $layout, string $view, array $data = []): Response
    {
        return Response::html(View::renderWithLayout($layout, $view, $data));
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $to): Response
    {
        return Response::redirect($to);
    }

    protected function back(string $fallback = '/'): Response
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? null;

        return Response::redirect($referer !== null ? parse_url($referer, PHP_URL_PATH) ?: $fallback : $fallback);
    }

    protected function withError(string $message, string $to): Response
    {
        Session::flash('error', $message);

        return $this->redirect($to);
    }

    protected function withSuccess(string $message, string $to): Response
    {
        Session::flash('success', $message);

        return $this->redirect($to);
    }
}
