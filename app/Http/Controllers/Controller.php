<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
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

    /**
     * بازگشت به صفحهٔ قبل — فقط اگر از همین سایت آمده باشد.
     *
     * مسیر ارجاع‌دهنده پیشوند نصب (مثلاً /reshen) را دارد و redirect
     * دوباره آن را اضافه می‌کرد؛ پس اینجا پیشوند برداشته می‌شود.
     */
    protected function back(string $fallback = '/'): Response
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($referer === '' || ($host !== '' && parse_url($referer, PHP_URL_HOST) !== explode(':', $host)[0])) {
            return $this->redirect($fallback);
        }

        $path = (string) (parse_url($referer, PHP_URL_PATH) ?: $fallback);
        $base = Request::basePath();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }
        $query = parse_url($referer, PHP_URL_QUERY);

        return $this->redirect($path . ($query ? '?' . $query : ''));
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

    /**
     * خطای اعتبارسنجی: پیام کلی + خطای هر فیلد + مقادیر واردشده، تا
     * کاربر فرم را از نو پر نکند.
     *
     * @param array<string,string> $fieldErrors
     */
    protected function invalid(Request $request, array $fieldErrors, string $to, ?string $message = null): Response
    {
        $old = $request->all();
        unset($old['_csrf']);
        // رمز هرگز در نشست نمی‌نشیند (فایل نشست روی دیسک است)
        foreach (array_keys($old) as $key) {
            if (str_contains((string) $key, 'password') || in_array($key, ['code', 'secret', 'api_key'], true)) {
                unset($old[$key]);
            }
        }
        Session::flash('_old', $old);
        Session::flash('field_errors', $fieldErrors);
        Session::flash('error', $message ?? 'لطفاً موارد مشخص‌شده را اصلاح کنید.');

        return $this->redirect($to);
    }

    protected function notFound(string $message = 'صفحه‌ای که دنبالش بودید پیدا نشد.'): Response
    {
        return Response::html(View::renderWithLayout('layouts.minimal', 'errors.404', ['title' => 'پیدا نشد', 'message' => $message]), 404);
    }

    protected function forbidden(): Response
    {
        return Response::html(View::render('errors.403'), 403);
    }
}
