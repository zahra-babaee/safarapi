<?php

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
    /**
     * @throws ValidationException
     */
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
    /**
     * @throws ValidationException
     */
    public function store(Request $request)
    {
        // اعتبارسنجی داده‌ها
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'exists:categories,id',
            'has_photo' => 'nullable|boolean',
            'images' => 'array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();

            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'خطاهای اعتبارسنجی رخ داده است.',
                $errors
            ), 400);
        }

        // پاکسازی کدهای HTML در توضیحات
        $validatedData = $validator->validated();
        $validatedData['description'] = Purifier::clean($validatedData['description']);

        if ($request->has('images')) {
            foreach ($request->file('images') as $image) {
                $imagePath = $image->store('images', 'public');
                $validatedData['description'] .= '<img src="' . asset('storage/' . $imagePath) . '" alt="Image">';
            }
        }

        // ذخیره مقاله با وضعیت در انتظار تایید
        $article = Article::query()->create([
            'title' => $validatedData['title'],
            'description' => $validatedData['description'],
            'category_id' => $validatedData['category_id'],
            'user_id' => auth()->id(),
            'status' => 'pending',
            'has_photo' => $validatedData['has_photo'] ?? false,
        ]);

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'مقاله با موفقیت ثبت شد.',
            $article
        ), 201);
    }
    /**
     * @throws ValidationException
     */
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
     */
    public function update(Request $request, $articleId)
    {
        // اعتبارسنجی داده‌ها
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required',
            'category_id' => 'required|exists:categories,id',
            'has_photo' => 'nullable|boolean',
            'images' => 'array',
            'images.*' => 'url',
        ], [
            'title.required' => 'عنوان نباید خالی باشد.',
            'title.string' => 'عنوان باید رشته باشد.',
            'title.max' => 'عنوان باید حداکثر 255 کاراکتر باشد.',
            'description.required' => 'متن نباید خالی باشد.',
            'category_id.required' => 'دسته‌بندی نباید خالی باشد.',
            'category_id.exists' => 'دسته‌بندی انتخاب شده معتبر نیست.',
            'has_photo.boolean' => 'مقدار عکس باید به صورت صحیح/غلط باشد.',
            'images.array' => 'تصاویر باید یک آرایه باشند.',
            'images.*.url' => 'هر تصویر باید یک URL معتبر باشد.',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'خطاهای اعتبارسنجی رخ داده است.',
                $errors
            ), 400);
        }

        // دریافت داده‌های اعتبارسنجی‌شده
        $validatedData = $validator->validated();

        // اضافه کردن آدرس تصاویر به متن مقاله
        if (isset($validatedData['images'])) {
            $validatedData['description'] = ''; // پاک کردن توضیحات قبلی
            foreach ($validatedData['images'] as $imageUrl) {
                $validatedData['description'] .= '<img src="' . $imageUrl . '" alt="Image">';
            }
        }

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
        ), 204);
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
        $articles = Article::query()->where('status', 'published')->with('category')->get();

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'لیست مقالات منتشر شده با موفقیت بازیابی شد.',
            $articles
        ));
    }
    /**
     * @OA\Get(
     *     path="/api/v1/articles/pending",
     *     summary="دریافت مقالات در حال انتظار تایید",
     *     tags={"مقالات"},
     *     @OA\Response(
     *         response=200,
     *         description="لیست مقالات در حال انتظار تایید با موفقیت بازیابی شد.",
     *     )
     * )
     */
    public function getPendingArticles()
    {
        $userId = auth()->id();

        $articles = Article::query()->where('user_id', $userId)
            ->where('status', 'pending')
            ->with('category')
            ->get();

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'لیست مقالات در حال انتظار تایید با موفقیت بازیابی شد.',
            $articles
        ));
    }
}
