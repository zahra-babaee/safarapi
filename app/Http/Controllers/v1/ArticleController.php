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
    private function translateAttractionType($type)
    {
        $translationMap = [
            'natural' => 'طبیعی',
            'historical' => 'تاریخی',
            'cultural' => 'فرهنگی',
            'tourism' => 'گردشگری',
            'religious' => 'مذهبی',
            'food' => 'غذا و خوراکی',
            'fun' => 'تفریحی',
            'tourism news' => 'اخبار گردشگری',
            'celebrities' => 'مشاهیر'
        ];

        return $translationMap[$type] ?? $type;
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

        // متغیر نگهدارنده ID عکس کاور
        $imageId = null;

        // بررسی و آپلود کاور در جدول temporary_images اگر وجود دارد
        if ($request->hasFile('cover_image')) {
            $coverImageFile = $request->file('cover_image');
            $imageName = time() . '.' . $coverImageFile->extension();

            // ذخیره فایل در مسیر موقت
            $coverImageFile->move(public_path('images'), $imageName);
            $tempImagePath = "images/$imageName";

            // ایجاد رکورد در جدول temporary_images
            $tempImage = TemporaryImage::query()->create([
                'path' => $tempImagePath,
                'user_id' => auth()->id(),
            ]);
        }

        // ذخیره مقاله با وضعیت در انتظار تایید
        $article = Article::query()->create([
            'title' => $validatedData['title'],
            'description' => $validatedData['description'],
            'category_id' => $validatedData['category_id'],
            'user_id' => auth()->id(),
            'status' => 'pending',
        ]);

        // انتقال عکس کاور از temporary_images به images و به‌روزرسانی مقاله با image_id
        if (isset($tempImage)) {
            // انتقال عکس کاور به جدول images
            $finalImage = Image::query()->create([
                'path' => $tempImage->path,
                'type' => 'cover',
                'user_id' => auth()->id(),
            ]);

            // به‌روزرسانی مقاله با image_id
            $article->update(['image_id' => $finalImage->id]);

            // حذف عکس از جدول temporary_images
            $tempImage->delete();
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

        // پیدا کردن مقاله
        $article = Article::query()->findOrFail($articleId);
        $article->update([
            'title' => $validatedData['title'],
            'description' => $validatedData['description'],
            'category_id' => $validatedData['category_id'],
            'has_photo' => $validatedData['has_photo'] ?? false,
        ]);

        // بررسی آپلود عکس کاور جدید
        if ($request->hasFile('cover_image')) {
            $coverImage = $request->file('cover_image');

            // ذخیره عکس در پوشه‌ای مشخص و دریافت مسیر آن
            $path = $coverImage->store('images/covers', 'public');

            // ذخیره عکس در جدول images و تنظیم نوع آن به cover
            $finalImage = Image::query()->create([
                'path' => $path,
                'type' => 'cover',
                // فیلدهای اضافی در صورت نیاز
            ]);

            // به‌روزرسانی فیلد image_id در جدول articles
            $article->update(['image_id' => $finalImage->id]);
        }

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
     *     path="/api/v1/articles-all",
     *     summary="نمایش مقالات در حال انتظار",
     *     tags={"مقالات"},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="شماره صفحه",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="لیست مقالات با موفقیت بازیابی شد.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="OK"),
     *             @OA\Property(property="message", type="string", example="مقاله با موفقیت پیدا شد."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="article_id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", example="عنوان مقاله"),
     *                 @OA\Property(property="description", type="string", example="توضیحات مقاله"),
     *                 @OA\Property(property="abstract", type="string", example="خلاصه‌ای از مقاله"),
     *                 @OA\Property(property="cover_image", type="string", nullable=true, example="http://example.com/images/cover.jpg"),
     *                 @OA\Property(property="category_id", type="integer", example=1),
     *                 @OA\Property(property="category_name", type="string", example="نام دسته‌بندی"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-10-31 12:00"),
     *                 @OA\Property(
     *                     property="author",
     *                     type="object",
     *                     @OA\Property(property="name", type="string", nullable=true, example="نام نویسنده"),
     *                     @OA\Property(property="avatar", type="string", nullable=true, example="http://example.com/images/avatar.jpg")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="مقاله‌ای یافت نشد."
     *     )
     * )
     */
    public function showArticles()
    {
        $articles = Article::query()
            ->where('status', 'pending')
            ->with('category', 'image')
            ->select('id', 'title', 'description', 'image_id', 'category_id')
            ->orderBy('created_at', 'desc') // مرتب‌سازی بر اساس تاریخ انتشار
            ->paginate(10); // صفحه‌بندی

        $responseData = [
            'article_id' =>$articles->id,
            'title' => $articles->title,
            'description' => $articles->description,
            'abstract' => Str::limit(strip_tags($articles->description), 100), // حذف تگ‌های HTML
            'cover_image' => $articles->image ? asset($articles->image->path) : null,
            'category_id' => $articles->category_id,
            'category_name' => $articles->category ? $this->translateAttractionType($articles->category->attraction_type) : null,
            'created_at' => $articles->created_at->format('Y-m-d H:i'), // تاریخ ایجاد
            'author' => [
                    'name' => $articles->user ? $articles->user->name : null,
                    'avatar' => $articles->user && $articles->user->avatar ? asset($articles->user->avatar) : null
                ]
        ];

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'مقاله با موفقیت پیدا شد.',
            $responseData
        ), 200);
    }
    /**
     * @OA\Get(
     *     path="/api/v1/article/{id}",
     *     summary="نمایش جزئیات یک مقاله",
     *     tags={"مقالات"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="شناسه مقاله",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="مقاله با موفقیت بازیابی شد.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="OK"),
     *             @OA\Property(property="message", type="string", example="مقاله با موفقیت پیدا شد."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="article_id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", example="عنوان مقاله"),
     *                 @OA\Property(property="description", type="string", example="توضیحات مقاله"),
     *                 @OA\Property(property="abstract", type="string", example="خلاصه‌ای از مقاله"),
     *                 @OA\Property(property="cover_image", type="string", nullable=true, example="http://example.com/images/cover.jpg"),
     *                 @OA\Property(property="category_id", type="integer", example=1),
     *                 @OA\Property(property="category_name", type="string", example="نام دسته‌بندی"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-10-31 12:00"),
     *                 @OA\Property(
     *                     property="author",
     *                     type="object",
     *                     @OA\Property(property="name", type="string", nullable=true, example="نام نویسنده"),
     *                     @OA\Property(property="avatar", type="string", nullable=true, example="http://example.com/images/avatar.jpg")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="مقاله‌ای با این شناسه یافت نشد."
     *     )
     * )
     */
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
            'article_id'=> $article->id,
            'title' => $article->title,
            'description' => $article->description,
            'abstract' => Str::limit(strip_tags($article->description), 100), // حذف تگ‌های HTML
            'cover_image' => $article->image ? asset($article->image->path) : null,
            'category_id' => $article->category_id,
            'category_name' => $article->category ? $this->translateAttractionType($article->category->attraction_type) : null,
            'created_at' => $article->created_at->format('Y-m-d H:i'), // تاریخ ایجاد
            'author' => [
                'name' => $article->user ? $article->user->name : null,
                'avatar' => $article->user && $article->user->avatar ? asset($article->user->avatar) : null
            ]
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
    /**
     * @OA\Get(
     *     path="/api/v1/articles/search",
     *     summary="جستجوی مقالات",
     *     tags={"مقالات"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="کلیدواژه‌های جستجو برای عنوان و توضیحات مقالات",
     *         required=false,
     *         @OA\Schema(type="string", example="مقاله")
     *     ),
     *     @OA\Parameter(
     *         name="category_ids",
     *         in="query",
     *         description="شناسه‌های دسته‌بندی برای فیلتر کردن مقالات (با کاما جدا شده)",
     *         required=false,
     *         @OA\Schema(type="string", example="1,2")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="وضعیت مقاله (مثلاً published, pending)",
     *         required=false,
     *         @OA\Schema(type="string", example="published")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="نتایج جستجو با موفقیت بارگذاری شد.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="OK"),
     *             @OA\Property(property="message", type="string", example="نتایج جستجو با موفقیت بارگذاری شد."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="article_id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="عنوان مقاله"),
     *                     @OA\Property(property="description", type="string", example="توضیحات مقاله"),
     *                     @OA\Property(property="abstract", type="string", example="خلاصه‌ای از مقاله"),
     *                     @OA\Property(property="cover_image", type="string", nullable=true, example="http://example.com/images/cover.jpg"),
     *                     @OA\Property(property="category_id", type="integer", example=1),
     *                     @OA\Property(property="category_name", type="string", example="نام دسته‌بندی"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-10-31 12:00")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="مقاله‌ای با شرایط مشخص شده یافت نشد."
     *     )
     * )
     */
    public function search(Request $request)
    {
        $query = Article::query();

        // جستجو در عنوان و توضیحات با چندین کلمه
        if ($request->filled('query')) {
            $keywords = explode(' ', $request->input('query'));
            $query->where(function ($q) use ($keywords) {
                foreach ($keywords as $term) {
                    $q->orWhere('title', 'like', '%' . $term . '%');
                }
            });
        }

        // جستجو بر اساس چندین category_id
        if ($request->filled('category_ids')) {
            $categoryIds = explode(',', $request->input('category_ids'));
            $query->whereIn('category_id', $categoryIds);
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
                'article_id' => $article->id,
                'title' => $article->title,
                'description' => $article->description,
                'abstract' => Str::limit(strip_tags($article->description), 100), // حذف تگ‌های HTML
                'cover_image' => $article->image ? asset($article->image->path) : null,
                'category_id' => $article->category_id,
                'category_name' => $article->category ? $this->translateAttractionType($article->category->attraction_type) : null,
                'created_at' => $article->created_at->format('Y-m-d H:i'), // تاریخ ایجاد
            ];
        });

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'نتایج جستجو با موفقیت بارگذاری شد.',
            $formattedArticles
        ), 200);
    }
    /**
     * @OA\Get(
     *     path="/api/v1/articles/new",
     *     summary="دریافت مقالات جدید",
     *     tags={"مقالات"},
     *     @OA\Response(
     *         response=200,
     *         description="آخرین مقالات با موفقیت بارگذاری شد.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="OK"),
     *             @OA\Property(property="message", type="string", example="آخرین مقالات با موفقیت بارگذاری شد."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="article_id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="عنوان مقاله"),
     *                     @OA\Property(property="description", type="string", example="توضیحات مقاله"),
     *                     @OA\Property(property="abstract", type="string", example="خلاصه‌ای از مقاله"),
     *                     @OA\Property(property="cover_image", type="string", nullable=true, example="http://example.com/images/cover.jpg"),
     *                     @OA\Property(property="category_id", type="integer", example=1),
     *                     @OA\Property(property="category_name", type="string", example="نام دسته‌بندی"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-10-31 12:00")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function NewArticles()
    {
        $articles = Article::query()
            ->where('status', 'pending') // فیلتر برای مقالات منتشر شده
            ->orderBy('created_at', 'desc') // مرتب‌سازی بر اساس تاریخ ایجاد به صورت نزولی
            ->take(10) // تعداد مقالاتی که می‌خواهید نمایش دهید
            ->with('image') // بارگذاری تصویر کاور در صورت نیاز
            ->select('id', 'title', 'description', 'image_id', 'category_id', 'created_at') // انتخاب فیلدهای مورد نیاز
            ->get();

        $formattedArticles = $articles->map(function ($article) {
            return [
                'article_id' => $article->id,
                'title' => $article->title,
                'description' => $article->description,
                'abstract' => Str::limit(strip_tags($article->description), 100), // حذف تگ‌های HTML
                'cover_image' => $article->image ? asset($article->image->path) : null,
                'category_id' => $article->category_id,
                'category_name' => $article->category ? $this->translateAttractionType($article->category->attraction_type) : null,
                'created_at' => $article->created_at->format('Y-m-d H:i'), // تاریخ ایجاد
            ];
        });

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'آخرین مقالات با موفقیت بارگذاری شد.',
            $formattedArticles
        ), 200);
    }
    /**
     * @OA\Get(
     *     path="/api/v1/user/articles",
     *     summary="دریافت مقالات کاربر",
     *     tags={"مقالات"},
     *     @OA\Response(
     *         response=200,
     *         description="مقالات کاربر با موفقیت بارگذاری شد.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="OK"),
     *             @OA\Property(property="message", type="string", example="مقالات کاربر با موفقیت بارگذاری شد."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="article_id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="عنوان مقاله"),
     *                     @OA\Property(property="description", type="string", example="توضیحات مقاله"),
     *                     @OA\Property(property="abstract", type="string", example="خلاصه‌ای از مقاله"),
     *                     @OA\Property(property="cover_image", type="string", nullable=true, example="http://example.com/images/cover.jpg"),
     *                     @OA\Property(property="category_id", type="integer", example=1),
     *                     @OA\Property(property="category_name", type="string", example="نام دسته‌بندی"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-10-31 12:00")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function userArticles()
    {
        // فرض کنید کاربر جاری احراز هویت شده و از طریق `auth()->user()` در دسترس است
        $user = auth()->user();

        // مقالات کاربر را با اطلاعات مربوطه دریافت کنید
        $articles = $user->articles()->latest()->get();

        // مقالات را به فرمت مورد نظر تبدیل کنید
        $formattedArticles = $articles->map(function ($article) {
            return [
                'article_id' => $article->id,
                'title' => $article->title,
                'description' => $article->description,
                'abstract' => Str::limit(strip_tags($article->description), 100), // حذف تگ‌های HTML
                'cover_image' => $article->image ? url( $article->image->path) : null,
                'category_id' => $article->category_id,
                'category_name' => $article->category ? $this->translateAttractionType($article->category->attraction_type) : null,
                'created_at' => $article->created_at->format('Y-m-d H:i'),
            ];
        });

        return response()->json([
            'status' => 'OK',
            'message' => 'مقالات کاربر با موفقیت بارگذاری شد.',
            'data' => $formattedArticles,
        ]);
    }
}
