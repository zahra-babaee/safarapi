<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Image;
use App\Models\TemporaryImage;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/upload-new-image",
     *     summary="آپلود تصویر جدید",
     *     description="این متد تصویر ارسالی از سیکاادیتور آپلود می‌کند و اطلاعات آن را در دیتابیس ذخیره می‌کند.",
     *     tags={"تصاویر"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="image",
     *                     type="string",
     *                     format="binary",
     *                     description="فایل تصویری برای آپلود"
     *                 ),
     *                 @OA\Property(
     *                     property="is_default",
     *                     type="boolean",
     *                     description="تعیین این تصویر به عنوان تصویر پیش‌فرض"
     *                 ),
     *                 @OA\Property(
     *                     property="user_id",
     *                     type="integer",
     *                     description="شناسه کاربر مرتبط با تصویر (در صورت نیاز)"
     *                 ),
     *                 @OA\Property(
     *                     property="article_id",
     *                     type="integer",
     *                     description="شناسه مقاله مرتبط با تصویر (در صورت نیاز)"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="اطلاعات تصویر آپلود شده",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="image", type="string", example="1632938402.jpg", description="نام فایل تصویر"),
     *             @OA\Property(property="url", type="string", example="images/1632938402.jpg", description="مسیر فایل تصویر"),
     *             @OA\Property(property="status", type="string", example="success", description="وضعیت آپلود")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="خطای اعتبارسنجی",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="The given data was invalid.")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     security={{"bearerAuth":{}}}
     * )
     */
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
    /**
     * @OA\Post(
     *     path="/api/v1/upload/cover",
     *     summary="آپلود کاور مقاله به صورت موقت",
     *     tags={"عکس‌ها"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="cover_image",
     *                     type="string",
     *                     format="binary",
     *                     description="فایل عکس کاور"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="عکس به صورت موقتی ذخیره شد.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="عکس به صورت موقتی ذخیره شد."),
     *             @OA\Property(property="image_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطا در آپلود عکس"
     *     )
     * )
     */
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
    /**
     * @OA\Post(
     *     path="/api/v1/update/cover",
     *     summary="به‌روزرسانی کاور مقاله با عکس موقتی",
     *     tags={"عکس‌ها"},
     *     @OA\Parameter(
     *         name="articleId",
     *         in="path",
     *         required=true,
     *         description="شناسه مقاله",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="temporary_image_id",
     *                 type="integer",
     *                 description="شناسه عکس موقت",
     *                 example=1
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="کاور مقاله با موفقیت به‌روزرسانی شد.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="کاور مقاله با موفقیت به‌روزرسانی شد."),
     *             @OA\Property(property="image_id", type="integer", example=1),
     *             @OA\Property(property="image_path", type="string", example="images/cover.jpg")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="شما مجاز به ویرایش این مقاله نیستید."
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="عکس موقتی یافت نشد."
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطا در به‌روزرسانی کاور"
     *     )
     * )
     */
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
