<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function upload(Request $request)
    {
        // اعتبارسنجی تصویر
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // حداکثر حجم 2 مگابایت
        ]);

        // بارگذاری تصویر
        $imagePath = $request->file('image')->store('images', 'public');

        // ساختن URL کامل برای تصویر
        $imageUrl = asset('storage/' . $imagePath);

        // بازگشت نتیجه به صورت JSON
        return response()->json([
            'status' => 'success',
            'message' => 'تصویر با موفقیت آپلود شد.',
            'image_url' => $imageUrl
        ], 201);
    }
}
