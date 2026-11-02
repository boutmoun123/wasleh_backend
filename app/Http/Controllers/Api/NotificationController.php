<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class NotificationController extends Controller {
    public function index(Request $request): JsonResponse {
        return response()->json(['status'=>'success','data'=>$request->user()->notifications()->latest()->paginate(25)]);
    }
    public function markRead(Request $request, string $id): JsonResponse {
        $note=$request->user()->notifications()->whereKey($id)->firstOrFail(); $note->markAsRead();
        return response()->json(['status'=>'success']);
    }
}
