<?php
/**
 * «دسترسی ندارید».
 *
 * عمداً نمی‌گوید پشت در چیست — ساختار دسترسی‌ها را لو نمی‌دهیم.
 */
echo App\Core\View::renderWithLayout('layouts.minimal', 'errors.403-body', ['title' => 'دسترسی ندارید']);
