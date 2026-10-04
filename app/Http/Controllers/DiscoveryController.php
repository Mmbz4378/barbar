<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Core\{DB,Request,Response};
use App\Domain\Salon\{DiscoveryRepository,WorkingHoursRepository};
use App\Domain\Customer\CustomerAuth;
use App\Domain\Catalog\ServiceRepository;
use App\Domain\Staff\StaffRepository;

final class DiscoveryController extends Controller
{
    public function index(Request $request): Response
    {
        $filters=[];
        foreach(['q','city','neighborhood','max_price','rating','page'] as $key) $filters[$key]=(string)$request->query($key,'');
        $filters['favorites']=$request->path==='/me/favorites';
        if($filters['favorites']&&!CustomerAuth::check()) return $this->redirect('/me/login');
        $rows=(new DiscoveryRepository())->search($filters,CustomerAuth::phone());
        return $this->page('layouts.discover','discover.index',['title'=>$filters['favorites']?'علاقه‌مندی‌ها':'آرایشگاه بعدی تو','salons'=>array_slice($rows,0,12),'hasNext'=>count($rows)>12,'filters'=>$filters]);
    }
    public function show(Request $request): Response
    {
        $salon=(new DiscoveryRepository())->find((string)$request->param('slug'));
        if(!$salon) return Response::html('این سالن در فهرست عمومی موجود نیست.',404);
        $id=(int)$salon['id'];
        return $this->page('layouts.discover','discover.show',[
          'title'=>$salon['name'],'salon'=>$salon,'services'=>(new ServiceRepository())->all($id,true),
          'staff'=>(new StaffRepository())->all($id,true),'hours'=>(new WorkingHoursRepository())->salonDefaults($id),
          'reviews'=>DB::select("SELECT rating,comment,created_at,id FROM reviews WHERE salon_id=? AND moderation_status='published' ORDER BY id DESC LIMIT 30",[$id]),
          'favorite'=>CustomerAuth::check()&&DB::selectOne('SELECT salon_id FROM salon_favorites WHERE phone=? AND salon_id=?',[CustomerAuth::phone(),$id])!==null,
          'reviewSummary'=>DB::selectOne("SELECT COUNT(*) AS total,AVG(rating) AS average FROM reviews WHERE salon_id=? AND moderation_status='published'",[$id]),
        ]);
    }
    public function favorite(Request $request): Response
    {
        if(!CustomerAuth::check())return $this->redirect('/me/login');
        $salon=(new DiscoveryRepository())->find((string)$request->param('slug'));
        if(!$salon)return Response::html('یافت نشد.',404);
        if($request->input('saved')==='1') DB::statement('INSERT IGNORE INTO salon_favorites(phone,salon_id) VALUES (?,?)',[CustomerAuth::phone(),$salon['id']]);
        else DB::delete('salon_favorites','phone=? AND salon_id=?',[CustomerAuth::phone(),$salon['id']]);
        return $this->withSuccess('علاقه‌مندی‌ها به‌روز شد.','/salons/view/'.$salon['slug']);
    }
    public function review(Request $request): Response
    {
        if(!CustomerAuth::check())return $this->redirect('/me/login');
        $rating=filter_var($request->input('rating'),FILTER_VALIDATE_INT);
        if($rating===false||$rating<1||$rating>5)return $this->withError('امتیاز باید بین ۱ و ۵ باشد.','/me');
        $saved=DB::transaction(function()use($request,$rating){
          $a=DB::selectOne("SELECT a.* FROM appointments a JOIN customers c ON c.id=a.customer_id AND c.salon_id=a.salon_id WHERE a.id=? AND c.phone=? AND a.status='completed' FOR UPDATE",[(int)$request->param('id'),CustomerAuth::phone()]);
          if(!$a||DB::selectOne('SELECT id FROM reviews WHERE appointment_id=?',[$a['id']]))return false;
          DB::insert('reviews',['salon_id'=>$a['salon_id'],'appointment_id'=>$a['id'],'customer_id'=>$a['customer_id'],'rating'=>$rating,'comment'=>mb_substr(trim((string)$request->input('comment','')),0,500),'moderation_status'=>'pending']);return true;
        });
        return $saved?$this->withSuccess('نظر شما برای بررسی ثبت شد.','/me'):$this->withError('فقط یک نظر برای نوبت انجام‌شدهٔ خودتان می‌توانید ثبت کنید.','/me');
    }
    public function report(Request $request): Response
    {
        if(!CustomerAuth::check())return $this->redirect('/me/login');
        $reason=trim((string)$request->input('reason',''));
        if($reason==='')return $this->withError('دلیل گزارش را بنویسید.','/discover');
        $review=DB::selectOne("SELECT r.id,s.slug FROM reviews r JOIN salons s ON s.id=r.salon_id WHERE r.id=? AND r.moderation_status='published' AND ".DiscoveryRepository::VISIBLE,[(int)$request->param('id')]);
        if(!$review)return Response::html('یافت نشد.',404);
        DB::statement('INSERT IGNORE INTO review_reports(review_id,phone,reason) VALUES(?,?,?)',[$review['id'],CustomerAuth::phone(),mb_substr($reason,0,300)]);
        return $this->withSuccess('گزارش برای بررسی ثبت شد.','/salons/view/'.$review['slug']);
    }
}
