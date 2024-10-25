<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
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
}
