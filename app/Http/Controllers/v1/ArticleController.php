<?php

namespace App\Http\Controllers\v1;

use App\Dto\BaseDto;
use App\Dto\BaseDtoStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Image;
use App\Models\TemporaryImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mews\Purifier\Facades\Purifier;

class ArticleController extends Controller
{
    private function validateArticle(Request $request)
    {
        return Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'has_photo' => 'nullable|boolean',
            'images' => 'array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);
    }

    public function show1($id)
    {
        $article = Article::with('images')->findOrFail($id);
        return response()->json($article);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/articles",
     *     summary="ثبت مقاله",
     *     tags={"مقالات"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "description", "category_id"},
     *             @OA\Property(property="title", type="string", example="عنوان مقاله"),
     *             @OA\Property(property="description", type="string", example="متن مقاله"),
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="has_photo", type="boolean", example=true),
     *             @OA\Property(property="images", type="array", @OA\Items(type="string")),
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="مقاله با موفقیت ثبت شد.",
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="خطاهای اعتبارسنجی رخ داده است.",
     *     )
     * )
     * @throws ValidationException
     */

//    public function store(Request $request)
//    {
//        $validator = $this->validateArticle($request);
//
//        if ($validator->fails()) {
//            return response()->json(new BaseDto(
//                BaseDtoStatusEnum::ERROR,
//                'خطاهای اعتبارسنجی رخ داده است.',
//                $validator->errors()->toArray()
//            ), 400);
//        }
//
//        // پاکسازی کدهای HTML در توضیحات
//        $validatedData = $validator->validated();
//        $validatedData['description'] = Purifier::clean($validatedData['description'], function($config) {
//            $config->set('HTML.SafeIframe', true);
//            $config->set('HTML.Allowed', 'p,b,strong,i,a[href],img[src|alt|width|height]'); // اجزای مجاز
//            return $config;
//        });
//
//        // ذخیره مقاله با وضعیت در انتظار تایید
//        $article = Article::query()->create([
//            'title' => $validatedData['title'],
//            'description' => $validatedData['description'], // متن شامل لینک تصاویر
//            'category_id' => $validatedData['category_id'],
//            'user_id' => auth()->id(),
//            'status' => 'pending', //or published
//        ]);
//
//        return response()->json(new BaseDto(
//            BaseDtoStatusEnum::OK,
//            'مقاله با موفقیت ثبت شد.',
//            $article
//        ), 201);
//    }
    public function store(Request $request)
    {
        // اعتبارسنجی ورودی‌ها
        $validator = $this->validateArticle($request);

        if ($validator->fails()) {
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'خطاهای اعتبارسنجی رخ داده است.',
                $validator->errors()->toArray()
            ), 400);
        }

        // پاکسازی کدهای HTML در توضیحات
        $validatedData = $validator->validated();
        $validatedData['description'] = Purifier::clean($validatedData['description'], function($config) {
            $config->set('HTML.SafeIframe', true);
            $config->set('HTML.Allowed', 'p,b,strong,i,a[href],img[src|alt|width|height]'); // اجزای مجاز
            return $config;
        });

        // ذخیره مقاله با وضعیت در انتظار تایید
        $article = Article::query()->create([
            'title' => $validatedData['title'],
            'description' => $validatedData['description'],
            'category_id' =>
                $validatedData['category_id'],
            'user_id' => auth()->id(),
            'status' => 'pending',
        ]);

        // بررسی آپلود کاور موقت
        if ($request->has('temporary_image_id')) {
            $tempImageId = $request->input('temporary_image_id');
            $tempImage = TemporaryImage::query()->find($tempImageId);

            // انتقال عکس کاور از temporary_images به images
            if ($tempImage) {
                $finalImage = Image::query()->create([
                    'path' => $tempImage->path,
                    'type' => 'cover'
                    // فیلدهای اضافی
                ]);

                // به‌روزرسانی مقاله با image_id
                $article->update(['image_id' => $finalImage->id]);

                // حذف عکس از جدول temporary_images
                $tempImage->delete();
            } else {
                return response()->json(new BaseDto(
                    BaseDtoStatusEnum::ERROR,
                    'عکس موقتی یافت نشد.',
                    []
                ), 404);
            }
        }

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'مقاله با موفقیت ثبت شد.',
            $article
        ), 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/articles/{articleId}",
     *     summary="به‌روزرسانی مقاله",
     *     tags={"مقالات"},
     *     @OA\Parameter(
     *         name="articleId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "description", "category_id"},
     *             @OA\Property(property="title", type="string", example="عنوان مقاله"),
     *             @OA\Property(property="description", type="string", example="متن مقاله"),
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="has_photo", type="boolean", example=true),
     *             @OA\Property(property="images", type="array", @OA\Items(type="string")),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="مقاله با موفقیت به‌روزرسانی شد.",
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="خطاهای اعتبارسنجی رخ داده است.",
     *     )
     * )
     * @throws ValidationException
     */
    public function update(Request $request, $articleId)
    {
        // اعتبارسنجی ورودی‌ها
        $validator = $this->validateArticle($request);

        if ($validator->fails()) {
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'خطاهای اعتبارسنجی رخ داده است.',
                $validator->errors()->toArray()
            ), 400);
        }

        $validatedData = $validator->validated();

        // پاکسازی محتوای توضیحات مقاله
        $validatedData['description'] = Purifier::clean($validatedData['description'], function ($config) {
            $config->set('HTML.SafeIframe', true);
            $config->set('HTML.Allowed', 'p,b,strong,i,a[href],img[src|alt|width|height]'); // تگ‌های مجاز
            return $config;
        });

        // پیدا کردن مقاله و به‌روزرسانی آن
        $article = Article::query()->findOrFail($articleId);
        $article->update([
            'title' => $validatedData['title'],
            'description' => $validatedData['description'],
            'category_id' => $validatedData['category_id'],
            'has_photo' => $validatedData['has_photo'] ?? false,
        ]);

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'مقاله با موفقیت به‌روزرسانی شد.',
            $article
        ), 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/articles/{articleId}",
     *     summary="حذف مقاله",
     *     tags={"مقالات"},
     *     @OA\Parameter(
     *         name="articleId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="مقاله با موفقیت حذف شد."
     *     )
     * )
     */
    public function destroy($articleId)
    {
        // پیدا کردن مقاله و حذف آن
        $article = Article::query()->findOrFail($articleId);
        $article->delete();

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'مقاله با موفقیت حذف شد.'
        ), 200);  // وضعیت 200 با محتوای JSON
    }

    /**
     * @OA\Get(
     *     path="/api/v1/articles/published",
     *     summary="دریافت مقالات منتشر شده",
     *     tags={"مقالات"},
     *     @OA\Response(
     *         response=200,
     *         description="لیست مقالات منتشر شده با موفقیت بازیابی شد.",
     *     )
     * )
     */
    public function getPublishedArticles()
    {
        $articles = Article::query()
            ->where('status', 'published')
            ->with('category')
            ->paginate(10);

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'مقالات منتشر شده با موفقیت بارگذاری شدند.',
            $articles
        ), 200);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/articles/pending",
     *     summary="دریافت مقالات در انتظار تایید",
     *     tags={"مقالات"},
     *     @OA\Response(
     *         response=200,
     *         description="لیست مقالات در انتظار تایید با موفقیت بازیابی شد.",
     *     )
     * )
     */
//    public function getPendingArticles()
//    {
//        $articles = Article::query()
//            ->where('status', 'pending')
//            ->with('category', 'image')
//            ->select('id', 'title', 'description', 'image_id', 'category_id')
//            ->paginate(10);
//
//        // فرمت داده‌ها برای نمایش
//        $formattedArticles = $articles->map(function ($article) {
//            return [
//                'id' => $article->id,
//                'title' => $article->title,
//                'description' => $article->description,
//                'cover_image' => $article->image ? asset($article->image->path) : null,
//                'category' => $article->category ? $article->category->name : null,
//            ];
//        });
//
//        return response()->json(new BaseDto(
//            BaseDtoStatusEnum::OK,
//            'مقالات در انتظار تایید با موفقیت بارگذاری شدند.',
//            $formattedArticles
//        ), 200);
//    }
    public function showArticles()
    {
        $articles = Article::query()
            ->where('status', 'pending')
            ->with('category', 'image')
            ->select('id', 'title', 'description', 'image_id', 'category_id')
            ->orderBy('created_at', 'desc') // مرتب‌سازی بر اساس تاریخ انتشار
            ->paginate(10); // صفحه‌بندی

        // فرمت‌دهی به خروجی
        $formattedArticles = $articles->map(function ($article) {
            return [
                'id' => $article->id,
                'title' => $article->title,
                'description' => Str::limit(strip_tags($article->description), 100), // نمایش خلاصه‌ای از مقاله
                'cover_image' => $article->image ? asset($article->image->path) : null,
                'category' => $article->category ? $article->category->name : null,
            ];
        });

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'مقالات در انتظار تایید با موفقیت بارگذاری شدند.',
            [
                'articles' => $formattedArticles,
                'pagination' => $articles->links()
            ]
        ), 200);
    }

    public function show($id)
    {
        // جستجو برای مقاله با شناسه مشخص
        $article = Article::with('image')->find($id);

        // اگر مقاله پیدا نشد، خطای 404 برگردانید
        if (!$article) {
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'مقاله‌ای با این شناسه یافت نشد.',
                []
            ), 404);
        }

        // فرمت داده‌ها برای نمایش
        $responseData = [
            'title' => $article->title,
            'description' => $article->description,
            'cover_image' => $article->image ? $article->image->url : null, // استفاده از متد getUrlAttribute
            'category_id' => $article->category_id
            // تاریخ و ساعت هم لازمه
        ];

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'مقاله با موفقیت پیدا شد.',
            $responseData
        ), 200);
    }

    public function uploadImage(Request $request)
    {
        // اعتبارسنجی ورودی
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:3146', // تصویر باید ارائه شود و حداکثر اندازه آن 3 مگابایت باشد
        ]);

        // نام فایل را ایجاد می‌کنیم
        $nameFile = time() . '.' . $request->image->extension();

        // فایل را در مسیر public/images ذخیره می‌کنیم
        $request->image->move(public_path('images'), $nameFile);

        // مسیر فایل در سرور
        $main_path_file = "images/$nameFile"; // دیگر نیازی به 'public' نیست

        // ذخیره اطلاعات تصویر در دیتابیس
        $image = Image::query()->create([
            'path' => $main_path_file,
            'type' => 'article',
            'is_default' => $request->input('is_default', false), // مقدار پیش‌فرض false
            'user_id' => $request->user_id, // اگر مرتبط با کاربر باشد
            'article_id' => $request->article_id, // اگر مرتبط با مقاله باشد
        ]);

        // تولید URL کامل
        $full_url = url("images/$nameFile");

        // بازگشت پاسخ
        return response()->json([
            'image' => $nameFile,
            'url' => $full_url, // ارسال URL کامل
            'status' => 'success'
        ]);
    }
    public function search(Request $request)
    {
        $query = Article::query();

        // اگر کاربر عنوان را وارد کرده باشد، براساس عنوان جستجو کنید
        if ($request->has('title')) {
            $query->where('title', 'like', '%' . $request->input('title') . '%');
        }

        // اگر کاربر دسته‌بندی را وارد کرده باشد، براساس دسته‌بندی فیلتر کنید
        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->has('created_at')) {
            $query->where('created_at', '=', $request->input('created_at'));
        }

        // جستجو بر اساس توضیحات
        if ($request->has('description')) {
            $query->where('description', 'like', '%' . $request->input('description') . '%');
        }

        // فیلتر وضعیت مقاله (اختیاری)
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // اجرا کردن کوئری و گرفتن نتایج
        $articles = $query->with('category', 'image')->get();

        // چک کردن اینکه آیا نتیجه‌ای وجود دارد یا خیر
        if ($articles->isEmpty()) {
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::OK,
                'نتایج جستجو یافت نشد.',
                []
            ), 200);
        }

        // فرمت‌دهی و بازگرداندن نتایج
        $formattedArticles = $articles->map(function ($article) {
            return [
                'id' => $article->id,
                'title' => $article->title,
                'description' => $article->description,
                'cover_image' => $article->image ? asset($article->image->path) : null,
                'category' => $article->category ? $article->category->name : null,
                'created_at' => $article->created_at,
            ];
        });

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'نتایج جستجو با موفقیت بارگذاری شد.',
            $formattedArticles
        ), 200);
    }
}
