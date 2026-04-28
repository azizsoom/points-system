<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

function riyadh_dt($value): string
{
    return $value ? Carbon::parse($value)->timezone('Asia/Riyadh')->format('Y-m-d H:i') : '-';
}

function admin_ui(string $title, string $body, ?object $user = null): string
{
    $user = $user ?: current_user_record();
    $version = e('1.4.1');
    $now = riyadh_dt(now());
    $token = csrf_token();
    $links = '<a class="nav-link" href="/dashboard">الرئيسية</a><a class="nav-link" href="/invoices">الفواتير والنقاط</a><a class="nav-link" href="/agents">المناديب</a><a class="nav-link" href="/payouts">الصرف والتصفير</a><a class="nav-link" href="/branches">الفروع</a><a class="nav-link" href="/employees">الموظفون</a><a class="nav-link" href="/agent/login">بوابة المندوب</a>';
    $logout = '<form method="POST" action="/logout"><input type="hidden" name="_token" value="'.$token.'"><button class="nav-link logout">تسجيل خروج</button></form>';
    $nav = $user ? '<div class="mobile-top"><details><summary><div><b>نظام مكافآت المناديب</b><small>اضغط لاختيار القسم</small></div><span>⌄</span></summary><nav>'.$links.$logout.'</nav></details></div><aside class="side"><h2>نظام مكافآت المناديب</h2><p>لوحة التحكم الداخلية</p><div class="ver">تحديث '.$version.'<br>توقيت الرياض '.$now.'</div><nav>'.$links.$logout.'</nav></aside>' : '';
    $flash = '';
    foreach (['success'=>'ok','error'=>'bad'] as $k=>$c) if (session($k)) $flash .= '<div class="flash '.$c.'">'.e(session($k)).'</div>';
    return '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).'</title><script src="https://cdn.tailwindcss.com"></script><style>*{box-sizing:border-box}body{margin:0;background:#f8fafc;color:#0f172a;font-family:Tahoma,Arial,sans-serif}.app{min-height:100vh;display:flex}.side{width:290px;min-height:100vh;background:#020617;color:white;padding:22px;position:sticky;top:0}.side h2{font-size:22px;font-weight:900}.side p,.ver{color:#cbd5e1;font-size:13px;margin-top:8px}.ver{background:#0f172a;border:1px solid #1e293b;border-radius:16px;padding:12px;margin:18px 0}.nav-link{display:block;width:100%;text-align:right;color:white;text-decoration:none;border-radius:14px;padding:12px 14px;margin:5px 0}.nav-link:hover{background:#1e293b}.logout{border:0;cursor:pointer}.main{flex:1;padding:28px;min-width:0}.wrap{max-width:1400px;margin:auto}.mobile-top{display:none;position:sticky;top:0;z-index:50;background:#020617;color:white}.mobile-top summary{list-style:none;display:flex;align-items:center;justify-content:space-between;padding:14px 16px;cursor:pointer}.mobile-top small{display:block;color:#cbd5e1;margin-top:3px}.mobile-top nav{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:0 14px 14px}.mobile-top .nav-link{background:#0f172a;margin:0}.flash{border-radius:18px;padding:14px;margin-bottom:16px}.ok{background:#ecfdf5;color:#065f46}.bad{background:#fff1f2;color:#9f1239}.input{width:100%;border:1px solid #cbd5e1;border-radius:14px;padding:12px;background:white;font-size:16px}.btn{display:inline-flex;align-items:center;justify-content:center;border-radius:14px;padding:11px 16px;font-weight:800;text-decoration:none;border:0;cursor:pointer}.btn-primary{background:#0f172a;color:white}.btn-soft{background:#f1f5f9;color:#0f172a}.card{background:white;border:1px solid #e2e8f0;border-radius:24px;box-shadow:0 8px 20px rgba(15,23,42,.05)}.table-wrap{width:100%;overflow-x:auto}table{width:100%;border-collapse:collapse}th,td{padding:13px;border-bottom:1px solid #e2e8f0;text-align:right;white-space:nowrap}.stat{border-radius:26px;color:white;padding:22px;min-height:120px}.stat small{opacity:.85}.stat b{display:block;font-size:26px;margin-top:10px}.bar{border-radius:12px 12px 0 0;min-height:8px}.page-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:22px}.page-head h1{font-size:30px;font-weight:900}.chip{background:white;border:1px solid #e2e8f0;border-radius:18px;padding:10px 14px;color:#475569;font-size:13px}@media(max-width:1024px){.app{display:block}.side{display:none}.mobile-top{display:block}.main{padding:14px}.wrap{max-width:100%}.grid{grid-template-columns:1fr!important}.page-head{display:block}.page-head h1{font-size:24px}.card{border-radius:18px}table{min-width:820px;font-size:13px}.btn{width:100%;margin:3px 0}.mobile-top nav{grid-template-columns:1fr 1fr}}@media(max-width:480px){.mobile-top nav{grid-template-columns:1fr}.main{padding:10px}.stat b{font-size:21px}}</style></head><body><div class="app">'.$nav.'<main class="main"><div class="wrap"><div class="chip mb-4">رقم التحديث: <b>'.$version.'</b> — توقيت الرياض: <b>'.$now.'</b></div>'.$flash.$body.'</div></main></div></body></html>';
}

Route::get('/dashboard', function () {
    $user = require_user_record();
    $agents = DB::table('agents')->count();
    $invoices = DB::table('invoices')->count();
    $sales = (float) DB::table('invoices')->where('status','active')->sum('net_amount');
    $rewards = (float) DB::table('invoices')->where('status','active')->sum('reward_amount');
    $paid = (float) DB::table('payouts')->sum('amount');
    $available = max(0, $rewards - $paid);
    $stats = [['صافي المبيعات', money_fmt($sales), 'from-blue-700 to-sky-400'], ['المكافآت المستحقة', money_fmt($available), 'from-emerald-700 to-teal-400'], ['عدد الفواتير', $invoices, 'from-violet-700 to-fuchsia-400'], ['عدد المناديب', $agents, 'from-amber-600 to-orange-500']];
    $html = '<div class="page-head"><div><h1>لوحة المدير</h1><p class="text-slate-500">ملخص واضح للمبيعات والنقاط والفواتير</p></div></div><div class="grid lg:grid-cols-4 md:grid-cols-2 gap-4">';
    foreach ($stats as $s) $html .= '<div class="stat bg-gradient-to-br '.$s[2].'"><small>'.$s[0].'</small><b>'.$s[1].'</b></div>';
    $html .= '</div>';
    $months=[]; for($i=5;$i>=0;$i--){$d=now('Asia/Riyadh')->subMonths($i);$months[$d->format('Y-m')]=['label'=>$d->format('m/Y'),'sales'=>0];}
    foreach($months as $key=>$m){[$y,$mo]=explode('-',$key);$months[$key]['sales']=(float)DB::table('invoices')->where('status','active')->whereYear('created_at',$y)->whereMonth('created_at',$mo)->sum('net_amount');}
    $max=max(1,max(array_column($months,'sales')));
    $html .= '<div class="card p-6 mt-6"><h2 class="font-black text-xl mb-4">رسم بياني للمبيعات الشهرية</h2><div class="flex items-end gap-3 h-64 border-b border-slate-200 pb-2">';
    foreach($months as $m){$h=max(10,round(($m['sales']/$max)*220));$html.='<div class="flex-1 text-center"><div class="bar bg-gradient-to-t from-blue-700 to-sky-300 mx-auto" style="height:'.$h.'px"></div><div class="text-xs text-slate-500 mt-2">'.$m['label'].'</div><b class="text-xs">'.number_format($m['sales'],0).'</b></div>';}
    $latest = DB::table('invoices')->join('agents','agents.id','=','invoices.agent_id')->leftJoin('users','users.id','=','invoices.user_id')->select('invoices.*','agents.name as agent_name','agents.referral_code','users.name as user_name')->latest('invoices.id')->limit(12)->get();
    $html .= '</div></div><div class="card table-wrap mt-6"><div class="p-5"><h2 class="font-black text-xl">آخر الفواتير وتتبع النقاط</h2></div><table><tr><th>التاريخ والوقت</th><th>الفاتورة</th><th>المندوب</th><th>الكود</th><th>الموظف</th><th>الصافي</th><th>النسبة</th><th>المكافأة</th><th>الحالة</th></tr>';
    foreach($latest as $i)$html.='<tr><td>'.riyadh_dt($i->created_at).'</td><td>'.e($i->invoice_number).'</td><td>'.e($i->agent_name).'</td><td>'.e($i->referral_code).'</td><td>'.e($i->user_name).'</td><td>'.money_fmt($i->net_amount).'</td><td>'.$i->reward_rate.'%</td><td class="font-bold text-emerald-700">'.money_fmt($i->reward_amount).'</td><td>'.($i->status==='active'?'فعالة':'ملغاة').'</td></tr>';
    return admin_ui('لوحة المدير', $html.'</table></div>', $user);
});
