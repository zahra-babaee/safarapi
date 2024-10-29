<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Image;
use App\Models\TemporaryImage;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function uploadImagenew(Request $request)
    {
        // اعتبارسنجی ورودی
        $request->validate([
            'image' => 'required|image|max:2048', // تصویر باید ارائه شود و حداکثر اندازه آن 2 مگابایت باشد
        ]);

        // نام فایل را ایجاد می‌کنیم
        $nameFile = time() . '.' . $request->image->extension();

        // فایل را در مسیر public/images ذخیره می‌کنیم
        $request->image->move(public_path('images'), $nameFile);

        // مسیر فایل در سرور
        $main_path_file = "images/$nameFile";

        // ذخیره اطلاعات تصویر در دیتابیس
        $image = Image::query()->create([
            'path' => $main_path_file,
            'type' => 'article',
            'is_default' => $request->input('is_default', false), // مقدار پیش‌فرض false
            'user_id' => $request->user_id, // اگر مرتبط با کاربر باشد
            'article_id' => $request->article_id, // اگر مرتبط با مقاله باشد
        ]);

        // بازگشت پاسخ
        return response()->json([

            'image' => $nameFile,
            'url' => $main_path_file,
            'status' => 'success'

        ]);
    }

    public function uploadTemporaryCover(Request $request)
    {
        // اعتبارسنجی عکس
        $request->validate([
            'cover_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:3146',
        ]);

        $nameFile = time() . '.' . $request->file('cover_image')->extension();

        try {
            // ذخیره عکس در مسیر موقتی
            $request->file('cover_image')->move(public_path('images'), $nameFile);
            $main_path_file = "images/$nameFile"; // دیگر نیازی به 'public' نیست

            // ذخیره مسیر در دیتابیس موقتی
            $tempImage = TemporaryImage::query()->create(['path' => $main_path_file]);

            return response()->json([
                'message' => 'عکس به صورت موقتی ذخیره شد.',
                'image_id' => $tempImage->id,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطا در آپلود عکس: ' . $e->getMessage(),
            ], 500);
        }
    }
    public function updateCover(Request $request, $articleId)
    {
        // اعتبارسنجی ورودی
        $request->validate([
            'temporary_image_id' => 'required|exists:temporary_images,id', // عکس موقت باید وجود داشته باشد
        ]);

        // یافتن مقاله
        $article = Article::query()->findOrFail($articleId);

        // بررسی اینکه کاربر مجاز به ویرایش مقاله است
        if ($article->user_id !== auth()->id()) {
            return response()->json([
                'message' => 'شما مجاز به ویرایش این مقاله نیستید.',
            ], 403);
        }

        // پیدا کردن عکس موقت
        $tempImage = TemporaryImage::query()->find($request->input('temporary_image_id'));

        if (!$tempImage) {
            return response()->json([
                'message' => 'عکس موقتی یافت نشد.',
            ], 404);
        }

        try {
            // حذف کاور قبلی اگر وجود داشته باشد
            if ($article->coverImage) {
                if (file_exists(public_path($article->coverImage->path))) {
                    unlink(public_path($article->coverImage->path));
                }
                $article->coverImage->delete();
            }

            // انتقال عکس از جدول temporary_images به images
            $finalImage = Image::query()->create([
                'path' => $tempImage->path,
                'type' => 'cover',
                'user_id' => auth()->id(),
                'article_id' => $article->id,
            ]);

            // به‌روزرسانی مقاله با کاور جدید
            $article->update(['image_id' => $finalImage->id]);

            // حذف عکس از جدول temporary_images
            $tempImage->delete();

            return response()->json([
                'message' => 'کاور مقاله با موفقیت به‌روزرسانی شد.',
                'image_id' => $finalImage->id,
                'image_path' => $finalImage->path,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطا در به‌روزرسانی کاور: ' . $e->getMessage(),
            ], 500);
        }
    }
}
