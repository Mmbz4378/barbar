<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Domain\Content\PageRepository;
use App\Domain\System\AuditLog;
use App\Support\SafeMarkdown;

/**
 * صفحه‌های محتوایی سایت (درباره، قوانین، حریم خصوصی، تماس، …).
 *
 * محتوا با Markdown ساده نوشته می‌شود و با SafeMarkdown نمایش داده می‌شود؛
 * HTML و اسکریپت در متن به‌صورت متن ساده دیده می‌شوند.
 */
final class PlatformPageController extends Controller
{
    public function index(Request $request): Response
    {
        $pages = (new PageRepository())->all();
        $existing = array_column($pages, 'slug');

        return $this->page('layouts.platform', 'platform.pages.index', [
            'title' => 'صفحه‌ها',
            'pages' => $pages,
            'suggested' => array_diff_key(PageRepository::SUGGESTED, array_flip($existing)),
        ]);
    }

    public function create(Request $request): Response
    {
        $slug = (string) $request->query('slug', '');

        return $this->page('layouts.platform', 'platform.pages.form', [
            'title' => 'صفحهٔ تازه',
            'page' => [
                'id' => null,
                'slug' => $slug,
                'title' => PageRepository::SUGGESTED[$slug] ?? '',
                'body' => '',
                'meta_description' => '',
                'is_published' => 1,
                'show_in_footer' => 1,
                'sort_order' => 0,
            ],
        ]);
    }

    public function edit(Request $request): Response
    {
        $page = (new PageRepository())->find((int) $request->param('id'));
        if ($page === null) {
            return $this->notFound('صفحه یافت نشد.');
        }

        return $this->page('layouts.platform', 'platform.pages.form', [
            'title' => 'ویرایش ' . $page['title'],
            'page' => $page,
            'preview' => SafeMarkdown::render((string) $page['body']),
        ]);
    }

    public function store(Request $request): Response
    {
        return $this->persist($request, null);
    }

    public function update(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ((new PageRepository())->find($id) === null) {
            return $this->notFound('صفحه یافت نشد.');
        }

        return $this->persist($request, $id);
    }

    public function delete(Request $request): Response
    {
        $repo = new PageRepository();
        $page = $repo->find((int) $request->param('id'));
        if ($page === null) {
            return $this->notFound('صفحه یافت نشد.');
        }
        $repo->delete((int) $page['id']);
        AuditLog::record(null, 'page.deleted', 'page', (int) $page['id'], ['slug' => $page['slug']]);

        return $this->withSuccess('صفحهٔ «' . $page['title'] . '» حذف شد.', '/platform/pages');
    }

    private function persist(Request $request, ?int $id): Response
    {
        $repo = new PageRepository();
        $back = $id === null ? '/platform/pages/new' : '/platform/pages/' . $id . '/edit';
        $title = mb_substr(trim((string) $request->input('title', '')), 0, 150);
        $slug = strtolower(trim((string) $request->input('slug', '')));
        $body = (string) $request->input('body', '');
        $errors = [];

        if ($title === '') {
            $errors['title'] = 'عنوان صفحه را بنویسید.';
        }
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,78}[a-z0-9])?$/', $slug)) {
            $errors['slug'] = 'نشانی فقط حروف لاتین کوچک، عدد و خط تیره (مثل about یا terms).';
        } elseif ($repo->slugTaken($slug, $id)) {
            $errors['slug'] = 'صفحهٔ دیگری همین نشانی را دارد.';
        }
        if (trim($body) === '') {
            $errors['body'] = 'متن صفحه خالی است.';
        } elseif (mb_strlen($body) > 100000) {
            $errors['body'] = 'متن بیش از حد طولانی است.';
        }
        if ($errors !== []) {
            return $this->invalid($request, $errors, $back);
        }

        $savedId = $repo->save($id, [
            'title' => $title,
            'slug' => $slug,
            'body' => $body,
            'meta_description' => mb_substr(trim((string) $request->input('meta_description', '')), 0, 300) ?: null,
            'is_published' => $request->input('is_published') === '1' ? 1 : 0,
            'show_in_footer' => $request->input('show_in_footer') === '1' ? 1 : 0,
            'sort_order' => max(-999, min(999, (int) int_input($request->input('sort_order', '0')))),
        ]);
        AuditLog::record(null, $id === null ? 'page.created' : 'page.updated', 'page', $savedId, ['slug' => $slug]);

        return $this->withSuccess('صفحه ذخیره شد.', '/platform/pages/' . $savedId . '/edit');
    }
}
