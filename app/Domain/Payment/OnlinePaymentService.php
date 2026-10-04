<?php
declare(strict_types=1);
namespace App\Domain\Payment;
use App\Core\{Config,DB};
use App\Support\Money;
use RuntimeException;

final class OnlinePaymentService
{
    public function __construct(private readonly PaymentGatewayInterface $gateway) {}

    public function start(string $token): array
    {
        if(!$this->gateway->isEnabled())throw new RuntimeException('پرداخت آنلاین فعال نیست.');
        return DB::transaction(function()use($token){
            $a=DB::selectOne("SELECT * FROM appointments WHERE public_token=? AND status='completed' FOR UPDATE",[$token]);
            if(!$a)throw new RuntimeException('پرداخت پس از انجام خدمت امکان‌پذیر است.');
            if((new PaymentRepository())->forAppointment((int)$a['salon_id'],(int)$a['id']))throw new RuntimeException('این نوبت قبلاً تسویه شده است.');
            $pending=DB::selectOne("SELECT * FROM online_payment_attempts WHERE appointment_id=? AND status='pending' ORDER BY id DESC LIMIT 1",[$a['id']]);
            if($pending)return $pending;
            $amount=(int)DB::selectOne('SELECT COALESCE(SUM(price),0) amount FROM appointment_items WHERE appointment_id=? AND salon_id=?',[$a['id'],$a['salon_id']])['amount'];
            if($amount<=0)throw new RuntimeException('مبلغ قابل پرداخت نیست.');
            $base=rtrim((string)Config::get('app.url',''),'/');
            if(!preg_match('~^https?://~',$base))throw new RuntimeException('نشانی اصلی برنامه برای بازگشت پرداخت تنظیم نشده است.');
            $result=$this->gateway->request(Money::fromRials($amount),$base.'/payments/callback',['description'=>'تسویهٔ نوبت رشن']);
            if(!$result['ok']||empty($result['reference'])||empty($result['redirectUrl']))throw new RuntimeException('شروع پرداخت ممکن نشد. دوباره تلاش کنید.');
            $id=DB::insert('online_payment_attempts',['salon_id'=>$a['salon_id'],'appointment_id'=>$a['id'],'authority'=>$result['reference'],'redirect_url'=>$result['redirectUrl'],'amount'=>$amount]);
            return DB::selectOne('SELECT * FROM online_payment_attempts WHERE id=?',[$id]);
        });
    }
    public function verify(string $authority): array
    {
        $attempt=DB::selectOne('SELECT * FROM online_payment_attempts WHERE authority=?',[$authority]);
        if(!$attempt)throw new RuntimeException('مرجع پرداخت معتبر نیست.');
        return DB::transaction(function()use($attempt,$authority){
            // Same lock order as checkout and manual settlement.
            $a=DB::selectOne('SELECT * FROM appointments WHERE id=? AND salon_id=? FOR UPDATE',[$attempt['appointment_id'],$attempt['salon_id']]);
            $row=DB::selectOne('SELECT * FROM online_payment_attempts WHERE id=? FOR UPDATE',[$attempt['id']]);
            if($row['status']==='paid')return ['paid'=>true,'token'=>$a['public_token']];
            if($row['status']==='failed')return ['paid'=>false,'token'=>$a['public_token']];
            $result=$this->gateway->verify($authority,Money::fromRials((int)$row['amount']));
            if(!$result['ok'])throw new RuntimeException('نتیجهٔ پرداخت هنوز مشخص نیست. برای بررسی دوباره تلاش کنید.');
            $paid=(bool)$result['paid'];
            if($paid){
                if(empty($result['refId']))throw new RuntimeException('کد پیگیری پرداخت دریافت نشد.');
                if(!(new PaymentRepository())->forAppointment((int)$a['salon_id'],(int)$a['id']))DB::insert('payments',['salon_id'=>$a['salon_id'],'appointment_id'=>$a['id'],'method'=>'online','amount'=>$row['amount'],'tip_amount'=>0,'gateway_ref'=>$result['refId']]);
            }
            DB::update('online_payment_attempts',['status'=>$paid?'paid':'failed','reference_id'=>$result['refId']??null],'id=:id',['id'=>$row['id']]);
            return ['paid'=>$paid,'token'=>$a['public_token']];
        });
    }
}
