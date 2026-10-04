<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Core\{Auth,DB,Request,Response};
use App\Support\ImageUpload;

final class SalonPublicationController extends Controller
{
    public function edit(Request $request): Response
    {
        return $this->page('layouts.panel','panel.publication',['title'=>'معرفی عمومی سالن','salon'=>DB::selectOne('SELECT * FROM salons WHERE id=?',[Auth::salonId()])]);
    }
    public function save(Request $request): Response
    {
        $id=Auth::salonId();
        $data=['neighborhood'=>mb_substr(trim((string)$request->input('neighborhood','')),0,100),'introduction'=>mb_substr(trim((string)$request->input('introduction','')),0,1000)];
        foreach(['map_lat'=>90,'map_lng'=>180] as $field=>$limit) {
            $raw=trim((string)$request->input($field,''));
            if($raw!==''&&(!is_numeric($raw)||abs((float)$raw)>$limit))return $this->withError('مختصات جغرافیایی معتبر نیست.','/panel/publication');
            $data[$field]=$raw===''?null:(float)$raw;
        }
        if(($data['map_lat']===null)!==($data['map_lng']===null))return $this->withError('طول و عرض جغرافیایی را با هم وارد کنید.','/panel/publication');
        $upload=ImageUpload::saveImage($_FILES['cover']??null,BASE_PATH.'/public/uploads/media','salon-'.$id);
        if($upload['error'])return $this->withError($upload['error'],'/panel/publication');
        if($upload['ok'])$data['cover_path']=$upload['path'];
        $data['publication_status']=$request->input('request_publication')==='1'?'pending':'draft';
        DB::update('salons',$data,'id=:id',['id'=>$id]);
        return $this->withSuccess('اطلاعات ذخیره شد. درخواست انتشار پس از بررسی فعال می‌شود.','/panel/publication');
    }
    public function serviceImage(Request $request): Response
    {
        $id=(int)$request->param('id');$salonId=Auth::salonId();
        if(!DB::selectOne('SELECT id FROM services WHERE id=? AND salon_id=?',[$id,$salonId]))return Response::html('یافت نشد.',404);
        $upload=ImageUpload::saveImage($_FILES['image']??null,BASE_PATH.'/public/uploads/media','service-'.$id);
        if(!$upload['ok'])return $this->withError($upload['error']??'یک تصویر انتخاب کنید.','/panel/services/'.$id.'/edit');
        DB::update('services',['image_file'=>$upload['path']],'id=:id AND salon_id=:sid',['id'=>$id,'sid'=>$salonId]);
        return $this->withSuccess('تصویر خدمت ذخیره شد.','/panel/services/'.$id.'/edit');
    }
    public function moderation(Request $request): Response
    {
        return $this->page('layouts.panel','platform.moderation',['title'=>'بررسی انتشار','salons'=>DB::select("SELECT * FROM salons WHERE publication_status='pending' ORDER BY id DESC LIMIT 100"),'reviews'=>DB::select("SELECT r.*,s.name salon_name,(SELECT COUNT(*) FROM review_reports rp WHERE rp.review_id=r.id) report_count FROM reviews r JOIN salons s ON s.id=r.salon_id WHERE r.moderation_status='pending' OR (r.moderation_status='published' AND EXISTS(SELECT 1 FROM review_reports rp WHERE rp.review_id=r.id)) ORDER BY r.id DESC LIMIT 100")]);
    }
    public function moderate(Request $request): Response
    {
        $type=$request->input('type');$id=(int)$request->input('id');$approve=$request->input('decision')==='approve';
        if(!in_array($type,['salon','review'],true))return Response::html('درخواست نامعتبر.',422);
        if($type==='salon'&&$approve) {
            $salon=DB::selectOne('SELECT * FROM salons WHERE id=?',[$id]);
            if(!$salon||!$salon['is_active']||!$salon['city']||!$salon['address']||!$salon['phone']||!DB::selectOne('SELECT id FROM services WHERE salon_id=? AND is_active=1',[$id])||!DB::selectOne('SELECT id FROM staff WHERE salon_id=? AND is_active=1',[$id]))return $this->withError('اطلاعات تماس، شهر، آدرس، خدمت و آرایشگر فعال باید تکمیل باشند.','/platform/moderation');
        }
        DB::transaction(function()use($type,$id,$approve){
          DB::update($type==='salon'?'salons':'reviews',[$type==='salon'?'publication_status':'moderation_status'=>$approve?'published':($type==='salon'?'rejected':'hidden')],'id=:id',['id'=>$id]);
          if($type==='review'&&$approve)DB::delete('review_reports','review_id=?',[$id]);
          DB::insert('audit_logs',['actor_user_id'=>Auth::id(),'action'=>$approve?'publication_approve':'publication_reject','subject_type'=>$type,'subject_id'=>$id]);
        });
        return $this->withSuccess('نتیجهٔ بررسی ذخیره شد.','/platform/moderation');
    }
}
