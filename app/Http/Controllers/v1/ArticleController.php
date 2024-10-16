<?php
//
//namespace App\Http\Controllers\v1;
//
//use App\Dto\BaseDto;
//use App\Dto\BaseDtoStatusEnum;
//use App\Http\Controllers\Controller;
//use App\Models\Article;
//use Illuminate\Http\Request;
//use Illuminate\Support\Facades\Validator;
//use Illuminate\Validation\ValidationException;
//use Mews\Purifier\Facades\Purifier;
//
//class ArticleController extends Controller
//{
//    private function validateArticle(Request $request)
//    {
//        return Validator::make($request->all(), [
//            'title' => 'required|string|max:255',
//            'description' => 'required|string',
//            'category_id' => 'required|exists:categories,id',
//            'has_photo' => 'nullable|boolean',
//            'images' => 'array',
//            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048'
//        ]);
//    }
//
//    public function show($id)
//    {
//        $article = Article::with('images')->findOrFail($id);
//        return response()->json($article);
//    }
//
//    /**
//     * @OA\Post(
//     *     path="/api/v1/articles",
//     *     summary="ثبت مقاله",
//     *     tags={"مقالات"},
//     *     @OA\RequestBody(
//     *         required=true,
//     *         @OA\JsonContent(
//     *             required={"title", "description", "category_id"},
//     *             @OA\Property(property="title", type="string", example="عنوان مقاله"),
//     *             @OA\Property(property="description", type="string", example="متن مقاله"),
//     *             @OA\Property(property="category_id", type="integer", example=1),
//     *             @OA\Property(property="has_photo", type="boolean", example=true),
//     *             @OA\Property(property="images", type="array", @OA\Items(type="string")),
//     *         )
//     *     ),
//     *     @OA\Response(
//     *         response=201,
//     *         description="مقاله با موفقیت ثبت شد.",
//     *     ),
//     *     @OA\Response(
//     *         response=400,
//     *         description="خطاهای اعتبارسنجی رخ داده است.",
//     *     )
//     * )
//     * @throws ValidationException
//     */
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
//        $validatedData['description'] = Purifier::clean($validatedData['description']);
//
//        // اضافه کردن تصاویر به توضیحات
//        if ($request->has('images')) {
//            $imageUrls = []; // آرایه‌ای برای ذخیره URLهای تصاویر
//
//            foreach ($request->file('images') as $image) {
//                $imagePath = $image->store('images', 'public');
//                $imageUrls[] = asset('storage/' . $imagePath);
//            }
//
//            // اضافه کردن URLهای تصاویر به توضیحات
//            $validatedData['description'] .= '<img src="' . implode('" alt="Image"><img src="', $imageUrls) . '" alt="Image">';
//            $validatedData['has_photo'] = true; // به روز رسانی وضعیت has_photo
//        }
//
//        // ذخیره مقاله با وضعیت در انتظار تایید
//        $article = Article::query()->create([
//            'title' => $validatedData['title'],
//            'description' => $validatedData['description'],
//            'category_id' => $validatedData['category_id'],
//            'user_id' => auth()->id(),
//            'status' => 'pending',
//            'has_photo' => $validatedData['has_photo'] ?? false,
//        ]);
//
//        return response()->json(new BaseDto(
//            BaseDtoStatusEnum::OK,
//            'مقاله با موفقیت ثبت شد.',
//            $article
//        ), 201);
//    }
//
//    public function uploadImage(Request $request)
//    {
//        // اعتبارسنجی ورودی
//        $request->validate([
//            'image' => 'required|image|max:2048', // تصویر باید ارائه شود و حداکثر اندازه آن 2 مگابایت باشد
//        ]);
//
//        // بارگذاری تصویر
//        $path = $request->file('image')->store('images', 'public');
//
//        // بازگشت URL تصویر بارگذاری شده
//        return response()->json([
//            'url' => asset('storage/' . $path), // ساخت URL کامل با استفاده از تابع asset
//        ]);
//    }
//
//
//    /**
//     * @OA\Put(
//     *     path="/api/v1/articles/{articleId}",
//     *     summary="به‌روزرسانی مقاله",
//     *     tags={"مقالات"},
//     *     @OA\Parameter(
//     *         name="articleId",
//     *         in="path",
//     *         required=true,
//     *         @OA\Schema(type="integer")
//     *     ),
//     *     @OA\RequestBody(
//     *         required=true,
//     *         @OA\JsonContent(
//     *             required={"title", "description", "category_id"},
//     *             @OA\Property(property="title", type="string", example="عنوان مقاله"),
//     *             @OA\Property(property="description", type="string", example="متن مقاله"),
//     *             @OA\Property(property="category_id", type="integer", example=1),
//     *             @OA\Property(property="has_photo", type="boolean", example=true),
//     *             @OA\Property(property="images", type="array", @OA\Items(type="string")),
//     *         )
//     *     ),
//     *     @OA\Response(
//     *         response=200,
//     *         description="مقاله با موفقیت به‌روزرسانی شد.",
//     *     ),
//     *     @OA\Response(
//     *         response=400,
//     *         description="خطاهای اعتبارسنجی رخ داده است.",
//     *     )
//     * )
//     * @throws ValidationException
//     */
//    public function update(Request $request, $articleId)
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
//        $validatedData = $validator->validated();
//
//        // پیدا کردن مقاله و به‌روزرسانی آن
//        $article = Article::query()->findOrFail($articleId);
//        $article->update([
//            'title' => $validatedData['title'],
//            'description' => Purifier::clean($validatedData['description']),
//            'category_id' => $validatedData['category_id'],
//            'has_photo' => $validatedData['has_photo'] ?? false,
//        ]);
//
//        // اضافه کردن تصاویر جدید به توضیحات
//        if (isset($validatedData['images'])) {
//            foreach ($validatedData['images'] as $imageUrl) {
//                $article->description .= '<img src="' . $imageUrl . '" alt="Image">';
//            }
//        }
//
//        return response()->json(new BaseDto(
//            BaseDtoStatusEnum::OK,
//            'مقاله با موفقیت به‌روزرسانی شد.',
//            $article
//        ), 200);
//    }
//
//    /**
//     * @OA\Delete(
//     *     path="/api/v1/articles/{articleId}",
//     *     summary="حذف مقاله",
//     *     tags={"مقالات"},
//     *     @OA\Parameter(
//     *         name="articleId",
//     *         in="path",
//     *         required=true,
//     *         @OA\Schema(type="integer")
//     *     ),
//     *     @OA\Response(
//     *         response=204,
//     *         description="مقاله با موفقیت حذف شد."
//     *     )
//     * )
//     */
//    public function destroy($articleId)
//    {
//        // پیدا کردن مقاله و حذف آن
//        $article = Article::query()->findOrFail($articleId);
//        $article->delete();
//
//        return response()->json(new BaseDto(
//            BaseDtoStatusEnum::OK,
//            'مقاله با موفقیت حذف شد.'
//        ), 204);
//    }
//
//    /**
//     * @OA\Get(
//     *     path="/api/v1/articles/published",
//     *     summary="دریافت مقالات منتشر شده",
//     *     tags={"مقالات"},
//     *     @OA\Response(
//     *         response=200,
//     *         description="لیست مقالات منتشر شده با موفقیت بازیابی شد.",
//     *     )
//     * )
//     */
//    public function getPublishedArticles()
//    {
//        $articles = Article::query()
//            ->where('status', 'published')
//            ->with('category')
//            ->paginate(10);
//
//        return response()->json(new BaseDto(
//            BaseDtoStatusEnum::OK,
//            'مقالات منتشر شده با موفقیت بارگذاری شدند.',
//            $articles
//        ), 200);
//    }
//
//    /**
//     * @OA\Get(
//     *     path="/api/v1/articles/pending",
//     *     summary="دریافت مقالات در انتظار تایید",
//     *     tags={"مقالات"},
//     *     @OA\Response(
//     *         response=200,
//     *         description="لیست مقالات در انتظار تایید با موفقیت بازیابی شد.",
//     *     )
//     * )
//     */
//    public function getPendingArticles()
//    {
//        $articles = Article::query()
//            ->where('status', 'pending')
//            ->with('category')
//            ->paginate(10);
//
//        return response()->json(new BaseDto(
//            BaseDtoStatusEnum::OK,
//            'مقالات در انتظار تایید با موفقیت بارگذاری شدند.',
//            $articles
//        ), 200);
//    }
//}


namespace App\Http\Controllers\v1;

use App\Dto\BaseDto;
use App\Dto\BaseDtoStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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
        ]);
    }

    public function show($id)
    {
        $article = Article::with('images')->findOrFail($id);
        return response()->json($article);
    }

    public function store(Request $request)
    {
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
        $validatedData['description'] = Purifier::clean($validatedData['description']);

        // ذخیره مقاله با وضعیت در انتظار تایید
        $article = Article::query()->create([
            'title' => $validatedData['title'],
            'description' => $validatedData['description'],
            'category_id' => $validatedData['category_id'],
            'user_id' => auth()->id(),
            'status' => 'pending',
        ]);

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'مقاله با موفقیت ثبت شد.',
            $article
        ), 201);
    }

    public function update(Request $request, $articleId)
    {
        $validator = $this->validateArticle($request);

        if ($validator->fails()) {
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'خطاهای اعتبارسنجی رخ داده است.',
                $validator->errors()->toArray()
            ), 400);
        }

        $validatedData = $validator->validated();

        // پیدا کردن مقاله و به‌روزرسانی آن
        $article = Article::query()->findOrFail($articleId);
        $article->update([
            'title' => $validatedData['title'],
            'description' => Purifier::clean($validatedData['description']),
            'category_id' => $validatedData['category_id'],
        ]);

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'مقاله با موفقیت به‌روزرسانی شد.',
            $article
        ), 200);
    }

    public function destroy($articleId)
    {
        $article = Article::query()->findOrFail($articleId);
        $article->delete();

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'مقاله با موفقیت حذف شد.'
        ), 204);
    }

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

    public function getPendingArticles()
    {
        $articles = Article::query()
            ->where('status', 'pending')
            ->with('category')
            ->paginate(10);

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'مقالات در انتظار تایید با موفقیت بارگذاری شدند.',
            $articles
        ), 200);
    }
}
