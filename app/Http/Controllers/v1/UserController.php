<?php

namespace App\Http\Controllers\v1;

use App\Exceptions\UserException;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        $users = User::all();
        return response()->json($users);

//        $users = User::all();
//        if (User::count() < 5){
//            throw new UserException();
//        }

        return new UserResource($users);

    }
}
