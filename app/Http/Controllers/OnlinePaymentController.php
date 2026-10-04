<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Core\{Request,Response};
use App\Domain\Payment\{OnlinePaymentService,PaymentGatewayManager};
final class OnlinePaymentController extends Controller
{
    public function start(Request $request): Response
    {
        $token=(string)$request->param('token');
        try{$attempt=(new OnlinePaymentService(PaymentGatewayManager::gateway()))->start($token);return new Response('',303,['Location'=>$attempt['redirect_url']]);}
        catch(\RuntimeException $e){return $this->withError($e->getMessage(),'/q/'.$token);}
    }
    public function callback(Request $request): Response
    {
        try{$result=(new OnlinePaymentService(PaymentGatewayManager::gateway()))->verify((string)$request->query('Authority',''));}
        catch(\RuntimeException $e){return $this->page('layouts.customer','customer.payment-error',['title'=>'بررسی پرداخت','message'=>$e->getMessage()]);}
        return $result['paid']?$this->withSuccess('پرداخت تأیید شد.','/q/'.$result['token']):$this->withError('پرداخت تأیید نشد؛ می‌توانید دوباره تلاش کنید.','/q/'.$result['token']);
    }
}
