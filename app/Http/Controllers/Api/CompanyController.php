<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\Quota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['status'=>'success','data'=>$request->user()->company?->load('quota','documents')]);
    }
    public function save(Request $request): JsonResponse
    {
        $data=$request->validate([
            'name'=>['required','string','max:255'], 'industry'=>['nullable','string','max:120'],
            'city'=>['nullable','string','max:120'],'description'=>['nullable','string','max:3000'],
            'website'=>['nullable','url','max:255'],
        ]);
        $previous=$request->user()->company;
        $identityChanged=$previous && ($previous->name!==$data['name'] || $previous->website!==($data['website']??null));
        $company=Company::updateOrCreate(['user_id'=>$request->user()->id],$data);
        if ($identityChanged) { $company->update(['verification_status'=>'pending','verified_at'=>null]); }
        Quota::firstOrCreate(['company_id'=>$company->id],['available_posts'=>0]);
        return response()->json(['status'=>'success','data'=>$company],200);
    }
    public function uploadDocument(Request $request): JsonResponse
    {
        $company=$request->user()->company;
        if (! $company) { return response()->json(['message'=>'Create company profile first.'],422); }
        $request->validate(['document'=>['required','file','mimes:pdf,jpg,jpeg,png','max:5120']]);
        $file=$request->file('document'); $disk=config('wasla.private_disk','local');
        $path=$file->store('wasla/companies/'.$company->id,$disk);
        $record=CompanyDocument::create([
            'company_id'=>$company->id,'original_name'=>$file->getClientOriginalName(),
            'disk'=>$disk,'path'=>$path,'mime'=>$file->getMimeType() ?: 'application/octet-stream',
            'size'=>$file->getSize(),
        ]);
        // New evidence requires verification again.
        $company->update(['verification_status'=>'pending','verified_at'=>null]);
        return response()->json(['status'=>'success','data'=>$record->only('id','original_name','review_status')],201);
    }
    public function downloadDocument(Request $request, CompanyDocument $document)
    {
        $company=$request->user()->company;
        abort_unless($company && $document->company_id === $company->id,403);
        abort_unless(Storage::disk($document->disk)->exists($document->path),404);
        return Storage::disk($document->disk)->download($document->path,$document->original_name);
    }
}
