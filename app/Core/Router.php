<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int,array{method:string,pattern:string,regex:string,handler:mixed,middleware:array}> */
    private array $routes = [];

    private array $groupMiddleware = [];

    private string $groupPrefix = '';

    public function group(array $options, callable $callback): void
    {
        $previousMiddleware = $this->groupMiddleware;
        $previousPrefix = $this->groupPrefix;

        $this->groupMiddleware = array_merge($this->groupMiddleware, $options['middleware'] ?? []);
        $this->groupPrefix .= $options['prefix'] ?? '';

        $callback($this);

        $this->groupMiddleware = $previousMiddleware;
        $this->groupPrefix = $previousPrefix;
    }

    public function get(string $pattern, mixed $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, mixed $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function put(string $pattern, mixed $handler): void
    {
        $this->add('PUT', $pattern, $handler);
    }

    public function delete(string $pattern, mixed $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    private function add(string $method, string $pattern, mixed $handler): void
    {
        $fullPattern = rtrim($this->groupPrefix . $pattern, '/');
        $fullPattern = $fullPattern === '' ? '/' : $fullPattern;

        $regex = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $fullPattern);

        $this->routes[] = [
            'method' => $method,
            'pattern' => $fullPattern,
            'regex' => '#^' . $regex . '$#u',
            'handler' => $handler,
            'middleware' => $this->groupMiddleware,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method === 'HEAD' ? 'GET' : $request->method;

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (!preg_match($route['regex'], $request->path, $matches)) {
                continue;
            }

            /*
             * پارامترها رمزگشایی می‌شوند، ولی خودِ مسیر نه.
             *
             * چرا اینجا و نه در Request: نشانی خام است، پس یک slug فارسی
             * به‌شکل %D8%A2... می‌رسد و هرگز با مقداری که در دیتابیس
             * نشسته جور نمی‌شود — صفحهٔ عمومی سالن ۴۰۴ می‌داد، و چون
             * آنبوردینگ از نام فارسی slug فارسی می‌سازد، این یعنی هر
             * سالنی که از مسیر عادی ساخته شود لینک عمومی‌اش کار نمی‌کند.
             *
             * ولی رمزگشایی کل مسیر پیش از تطبیق، خطرناک است: یک %2F
             * داخل مقدار، بعد از رمزگشایی «/» می‌شود و می‌تواند مسیر را
             * به جای دیگری ببرد. پس تطبیق روی نشانی خام انجام می‌شود —
             * جایی که [^/]+ واقعاً یعنی «بدون جداکنندهٔ مسیر» — و فقط
             * چیزی که گرفته شده رمزگشایی می‌شود.
             */
            $params = array_map(
                static fn (string $value): string => rawurldecode($value),
                array_filter($matches, static fn ($key) => !is_int($key), ARRAY_FILTER_USE_KEY)
            );
            $request->routeParams = $params;

            $pipeline = array_reverse($route['middleware']);
            $core = function (Request $req) use ($route): Response {
                return $this->invoke($route['handler'], $req);
            };

            $next = $core;
            foreach ($pipeline as $middlewareClass) {
                /*
                 * «کلاس:پارامتر» — مثل AbilityRequired::class . ':manage_salon'.
                 *
                 * بدون این، برای هر سطح دسترسی باید یک کلاس میدل‌ور جدا
                 * می‌ساختیم و فهرست اجازه‌ها در چند فایل پخش می‌شد.
                 */
                $argument = null;
                if (str_contains($middlewareClass, ':')) {
                    [$middlewareClass, $argument] = explode(':', $middlewareClass, 2);
                }

                $middleware = $argument === null
                    ? new $middlewareClass()
                    : new $middlewareClass($argument);
                $next = function (Request $req) use ($middleware, $next): Response {
                    return $middleware->handle($req, $next);
                };
            }

            return $next($request);
        }

        return Response::html($this->notFoundBody(), 404);
    }

    private function invoke(mixed $handler, Request $request): Response
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = new $class();

            return $controller->$method($request);
        }

        return $handler($request);
    }

    /**
     * صفحهٔ ۴۰۴.
     *
     * اگر رندر قالب خودش شکست بخورد (مثلاً دیتابیس در دسترس نیست)، یک
     * صفحهٔ سادهٔ خودبسنده برمی‌گردد تا ۵۰۰ روی ۴۰۴ سوار نشود.
     */
    private function notFoundBody(): string
    {
        try {
            return View::renderWithLayout('layouts.minimal', 'errors.404', [
                'title' => 'پیدا نشد',
                'message' => 'شاید نشانی را اشتباه وارد کرده‌اید، یا این صفحه دیگر وجود ندارد.',
            ]);
        } catch (\Throwable) {
            return '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><title>پیدا نشد</title>'
                . '<body style="font-family:sans-serif;text-align:center;padding:4rem">'
                . '<h1>این صفحه پیدا نشد.</h1><p><a href="' . htmlspecialchars(url('')) . '">بازگشت به صفحهٔ اصلی</a></p></body></html>';
        }
    }
}
