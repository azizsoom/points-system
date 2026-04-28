<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function money_fmt($value): string
{
    return number_format((float) $value, 2) . ' ريال';
}

function current_user_record()
{
    $id = session('user_id');
    return $id ? DB::table('users')->where('id', $id)->first() : null;
}

function require_user_record()
{
    $user = current_user_record();
    if (!$user) {
        abort(redirect('/login'));
    }
    if (!($user->active ?? true)) {
        session()->flush();
        abort(redirect('/login')->with('error', 'الحساب موقوف.'));
    }
    return $user;
}

function require_admin_record()
{
    $user = require_user_record();
    if (($user->role ?? 'staff') !== 'admin') {
        abort(403, 'هذه الصفحة مخصصة للإدارة فقط.');
    }
    return $user;
}

function app_layout(string $title, string $body, ?object $user = null): string
{
    $user = $user ?: current_user_record();
    $nav = $user ? '
    <div class="lg:hidden sticky top-0 z-50 bg-slate-950 text-white border-b border-slate-800">
        <details class="group">
            <summary class="flex items-center justify-between p-4 cursor-pointer list-none">
                <div>
                    <div class="text-lg font-black">نظام مكافآت المناديب</div>
                    <div class="text-slate-300 text-xs">اضغط لاختيار القسم</div>
                </div>
                <span class="text-2xl group-open:rotate-180 transition">⌄</span>
            </summary>
            <nav class="grid grid-cols-2 gap-2 p-4 pt-0 text-sm bg-slate-950">
                <a class="rounded-xl px-4 py-3 bg-slate-900 hover:bg-slate-800" href="/dashboard">الرئيسية</a>
                <a class="rounded-xl px-4 py-3 bg-slate-900 hover:bg-slate-800" href="/invoices">الفواتير والنقاط</a>
                <a class="rounded-xl px-4 py-3 bg-slate-900 hover:bg-slate-800" href="/agents">المناديب</a>
                <a class="rounded-xl px-4 py-3 bg-slate-900 hover:bg-slate-800" href="/payouts">الصرف والتصفير</a>
                <a class="rounded-xl px-4 py-3 bg-slate-900 hover:bg-slate-800" href="/branches">الفروع</a>
                <a class="rounded-xl px-4 py-3 bg-slate-900 hover:bg-slate-800" href="/employees">الموظفون</a>
                <a class="rounded-xl px-4 py-3 bg-slate-900 hover:bg-slate-800" href="/agent/login">بوابة المندوب</a>
                <form method="POST" action="/logout"><input type="hidden" name="_token" value="'.csrf_token().'">
                    <button class="w-full rounded-xl px-4 py-3 bg-red-950 hover:bg-red-900 text-right">خروج</button>
                </form>
            </nav>
        </details>
    </div>
    <aside class="hidden lg:block w-72 bg-slate-950 text-white p-5 min-h-screen sticky top-0">
        <div class="mb-8"><div class="text-2xl font-black">نظام مكافآت المناديب</div><div class="text-slate-300 text-sm mt-1">لوحة التحكم الداخلية</div></div>
        <nav class="space-y-2 text-sm">
            <a class="block rounded-xl px-4 py-3 hover:bg-slate-800" href="/dashboard">الرئيسية</a>
            <a class="block rounded-xl px-4 py-3 hover:bg-slate-800" href="/invoices">الفواتير والنقاط</a>
            <a class="block rounded-xl px-4 py-3 hover:bg-slate-800" href="/agents">المناديب</a>
            <a class="block rounded-xl px-4 py-3 hover:bg-slate-800" href="/payouts">الصرف والتصفير</a>
            <a class="block rounded-xl px-4 py-3 hover:bg-slate-800" href="/branches">الفروع</a>
            <a class="block rounded-xl px-4 py-3 hover:bg-slate-800" href="/employees">الموظفون</a>
            <a class="block rounded-xl px-4 py-3 hover:bg-slate-800" href="/agent/login">بوابة المندوب</a>
            <form method="POST" action="/logout"><input type="hidden" name="_token" value="'.csrf_token().'"><button class="w-full text-right rounded-xl px-4 py-3 hover:bg-red-900/50">تسجيل خروج</button></form>
        </nav>
    </aside>' : '';

    $flash = '';
    foreach (['success' => 'bg-emerald-50 text-emerald-800 border-emerald-200', 'error' => 'bg-rose-50 text-rose-800 border-rose-200'] as $key => $class) {
        if (session($key)) {
            $flash .= '<div class="mb-4 border rounded-2xl p-4 '.$class.'">'.e(session($key)).'</div>';
        }
    }

    return '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'.e($title).'</title><script src="https://cdn.tailwindcss.com"></script><style>body{font-family:Tahoma,Arial,sans-serif}.input{width:100%;border:1px solid #cbd5e1;border-radius:14px;padding:12px;background:white;font-size:16px}.btn{display:inline-flex;align-items:center;justify-content:center;border-radius:14px;padding:11px 18px;font-weight:700}.btn-primary{background:#0f172a;color:white}.btn-soft{background:#f1f5f9;color:#0f172a}.card{background:white;border:1px solid #e2e8f0;border-radius:22px;box-shadow:0 8px 20px rgba(15,23,42,.04)}th,td{padding:12px;border-bottom:1px solid #e2e8f0;text-align:right;white-space:nowrap}@media(max-width:768px){main{padding:14px!important}.card{border-radius:18px}h1{font-size:1.55rem!important}.grid{grid-template-columns:1fr!important}table{font-size:13px}.overflow-x-auto{overflow-x:auto;-webkit-overflow-scrolling:touch}.btn{width:100%;margin-top:4px}}</style></head><body class="bg-slate-50 text-slate-900"><div class="min-h-screen lg:flex">'.$nav.'<main class="flex-1 p-4 lg:p-8"><div class="max-w-7xl mx-auto">'.$flash.$body.'</div></main></div></body></html>';
}

function eligible_net(float $amount, float $discount): float
{
    return max(0, $amount - $discount);
}

function reward_amount(float $net, float $rate): float
{
    return round($net * ($rate / 100), 2);
}

function agent_balance(int $agentId): array
{
    $earned = (float) DB::table('invoices')->where('agent_id', $agentId)->where('status', 'active')->sum('reward_amount');
    $cancelled = (float) DB::table('invoices')->where('agent_id', $agentId)->where('status', 'cancelled')->sum('reward_amount');
    $paid = (float) DB::table('payouts')->where('agent_id', $agentId)->sum('amount');
    $available = max(0, $earned - $paid);
    return ['earned' => $earned, 'cancelled' => $cancelled, 'paid' => $paid, 'available' => $available, 'payable_now' => floor($available), 'fraction' => round($available - floor($available), 2)];
}

Route::get('/setup', function () {
    if (DB::table('users')->count() > 0) {
        return redirect('/login')->with('error', 'تم إنشاء حساب الإدارة سابقاً.');
    }
    $body = '<div class="max-w-xl mx-auto mt-12 card p-7"><h1 class="text-3xl font-black mb-2">تهيئة النظام</h1><p class="text-slate-500 mb-6">أنشئ أول حساب مدير للنظام.</p><form method="POST" action="/setup" class="space-y-4"><input type="hidden" name="_token" value="'.csrf_token().'"><input class="input" name="name" placeholder="اسم المدير" required><input class="input" name="email" type="email" placeholder="البريد الإلكتروني" required><input class="input" name="password" type="password" placeholder="كلمة المرور" required><button class="btn btn-primary w-full">إنشاء المدير</button></form></div>';
    return app_layout('تهيئة النظام', $body);
});

Route::post('/setup', function (Request $request) {
    if (DB::table('users')->count() > 0) return redirect('/login');
    $request->validate(['name' => 'required', 'email' => 'required|email', 'password' => 'required|min:6']);
    $id = DB::table('users')->insertGetId(['name' => $request->name, 'email' => $request->email, 'password' => Hash::make($request->password), 'role' => 'admin', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    session(['user_id' => $id]);
    return redirect('/dashboard')->with('success', 'تم إنشاء المدير بنجاح.');
});

Route::get('/login', function () {
    if (current_user_record()) return redirect('/dashboard');
    if (DB::table('users')->count() === 0) return redirect('/setup');
    $body = '<div class="max-w-md mx-auto mt-16 card p-7"><h1 class="text-3xl font-black mb-2">دخول الموظف</h1><p class="text-slate-500 mb-6">لإدارة الفواتير والنقاط والصرف.</p><form method="POST" action="/login" class="space-y-4"><input type="hidden" name="_token" value="'.csrf_token().'"><input class="input" name="email" type="email" placeholder="البريد الإلكتروني" required><input class="input" name="password" type="password" placeholder="كلمة المرور" required><button class="btn btn-primary w-full">دخول</button></form><a class="block text-center mt-4 text-slate-500" href="/agent/login">دخول المندوب</a></div>';
    return app_layout('تسجيل الدخول', $body);
});

Route::post('/login', function (Request $request) {
    $user = DB::table('users')->where('email', $request->email)->first();
    if (!$user || !Hash::check($request->password, $user->password)) return back()->with('error', 'بيانات الدخول غير صحيحة.');
    if (!($user->active ?? true)) return back()->with('error', 'الحساب موقوف.');
    session(['user_id' => $user->id]);
    return redirect('/dashboard');
});

Route::post('/logout', function () { session()->flush(); return redirect('/login'); });

Route::get('/', fn () => current_user_record() ? redirect('/dashboard') : redirect('/login'));

Route::get('/dashboard', function () {
    $user = require_user_record();
    $agents = DB::table('agents')->count();
    $invoices = DB::table('invoices')->count();
    $sales = (float) DB::table('invoices')->where('status', 'active')->sum('net_amount');
    $rewards = (float) DB::table('invoices')->where('status', 'active')->sum('reward_amount');
    $paid = (float) DB::table('payouts')->sum('amount');
    $cards = [['المناديب', $agents], ['الفواتير', $invoices], ['صافي المبيعات', money_fmt($sales)], ['المكافآت المستحقة', money_fmt($rewards - $paid)]];
    $html = '<h1 class="text-3xl font-black mb-6">الرئيسية</h1><div class="grid md:grid-cols-4 gap-4">';
    foreach ($cards as $c) $html .= '<div class="card p-5"><div class="text-slate-500 text-sm">'.$c[0].'</div><div class="text-2xl font-black mt-2">'.$c[1].'</div></div>';
    $html .= '</div><div class="grid lg:grid-cols-2 gap-6 mt-6"><div class="card p-6"><h2 class="font-black text-xl mb-3">طريقة الحساب</h2><p class="leading-8 text-slate-600">كل مندوب له نسبة خاصة. الافتراضي 0.5%. النظام يحسب المكافأة من صافي الفاتورة قبل الضريبة وبعد الخصم فقط. الشحن والرسوم الإضافية لا تدخل في الحساب.</p></div><div class="card p-6"><h2 class="font-black text-xl mb-3">الكسور</h2><p class="leading-8 text-slate-600">عند الصرف، يتم صرف الرقم الصحيح فقط تلقائياً، وتبقى الكسور في رصيد المندوب للشهر القادم.</p></div></div>';
    return app_layout('الرئيسية', $html, $user);
});

Route::get('/branches', function () {
    $user = require_user_record();
    $rows = DB::table('branches')->latest()->get();
    $html = '<div class="flex justify-between items-center mb-6"><h1 class="text-3xl font-black">الفروع</h1></div><div class="card p-5 mb-6"><form method="POST" action="/branches" class="grid md:grid-cols-3 gap-3"><input type="hidden" name="_token" value="'.csrf_token().'"><input class="input" name="name" placeholder="اسم الفرع" required><input class="input" name="code" placeholder="كود الفرع اختياري"><button class="btn btn-primary">إضافة فرع</button></form></div><div class="card overflow-x-auto"><table class="w-full"><tr><th>الفرع</th><th>الكود</th><th>الحالة</th></tr>';
    foreach ($rows as $r) $html .= '<tr><td>'.e($r->name).'</td><td>'.e($r->code).'</td><td>'.(($r->active ?? true) ? 'نشط' : 'موقوف').'</td></tr>';
    return app_layout('الفروع', $html.'</table></div>', $user);
});
Route::post('/branches', function (Request $request) { require_admin_record(); DB::table('branches')->insert(['name'=>$request->name,'code'=>$request->code,'active'=>true,'created_at'=>now(),'updated_at'=>now()]); return back()->with('success','تمت إضافة الفرع.'); });

Route::get('/employees', function () {
    $user = require_admin_record();
    $branches = DB::table('branches')->where('active', true)->get();
    $users = DB::table('users')->leftJoin('branches','branches.id','=','users.branch_id')->select('users.*','branches.name as branch_name')->get();
    $opts = '<option value="">بدون فرع</option>'; foreach ($branches as $b) $opts .= '<option value="'.$b->id.'">'.e($b->name).'</option>';
    $html = '<h1 class="text-3xl font-black mb-6">الموظفون</h1><div class="card p-5 mb-6"><form method="POST" action="/employees" class="grid md:grid-cols-5 gap-3"><input type="hidden" name="_token" value="'.csrf_token().'"><input class="input" name="name" placeholder="الاسم" required><input class="input" name="email" type="email" placeholder="البريد" required><input class="input" name="password" placeholder="كلمة المرور" required><select class="input" name="role"><option value="staff">موظف</option><option value="admin">مدير</option></select><select class="input" name="branch_id">'.$opts.'</select><button class="btn btn-primary md:col-span-5">إضافة موظف</button></form></div><div class="card overflow-x-auto"><table class="w-full"><tr><th>الاسم</th><th>البريد</th><th>الدور</th><th>الفرع</th></tr>';
    foreach ($users as $u) $html .= '<tr><td>'.e($u->name).'</td><td>'.e($u->email).'</td><td>'.e($u->role).'</td><td>'.e($u->branch_name).'</td></tr>';
    return app_layout('الموظفون', $html.'</table></div>', $user);
});
Route::post('/employees', function (Request $request) { require_admin_record(); $request->validate(['email'=>'required|email|unique:users,email']); DB::table('users')->insert(['name'=>$request->name,'email'=>$request->email,'password'=>Hash::make($request->password),'role'=>$request->role,'branch_id'=>$request->branch_id,'active'=>true,'created_at'=>now(),'updated_at'=>now()]); return back()->with('success','تمت إضافة الموظف.'); });

Route::get('/agents', function () {
    $user = require_user_record();
    $agents = DB::table('agents')->latest()->get();
    $html = '<div class="flex justify-between items-center mb-6"><h1 class="text-3xl font-black">المناديب</h1></div><div class="card p-5 mb-6"><form method="POST" action="/agents" class="grid md:grid-cols-6 gap-3"><input type="hidden" name="_token" value="'.csrf_token().'"><input class="input" name="name" placeholder="اسم المندوب" required><input class="input" name="phone" placeholder="الجوال"><input class="input" name="city" placeholder="المدينة"><input class="input" name="referral_code" placeholder="كود الإحالة" required><input class="input" name="commission_rate" type="number" step="0.001" value="0.5" placeholder="النسبة %"><button class="btn btn-primary">إضافة</button></form></div><div class="card overflow-x-auto"><table class="w-full"><tr><th>المندوب</th><th>الجوال</th><th>الكود</th><th>النسبة</th><th>الرصيد</th><th>إجراء</th></tr>';
    foreach ($agents as $a) { $bal = agent_balance($a->id); $html .= '<tr><td>'.e($a->name).'</td><td>'.e($a->phone).'</td><td><b>'.e($a->referral_code).'</b></td><td>'.e($a->commission_rate).'%</td><td>'.money_fmt($bal['available']).'</td><td><a class="btn btn-soft" href="/agents/'.$a->id.'/edit">تعديل</a></td></tr>'; }
    return app_layout('المناديب', $html.'</table></div>', $user);
});
Route::post('/agents', function (Request $request) { require_user_record(); $request->validate(['referral_code'=>'required|unique:agents,referral_code']); DB::table('agents')->insert(['name'=>$request->name,'phone'=>$request->phone,'city'=>$request->city,'referral_code'=>$request->referral_code,'commission_rate'=>$request->commission_rate ?: 0.5,'status'=>'active','created_at'=>now(),'updated_at'=>now()]); return back()->with('success','تمت إضافة المندوب.'); });
Route::get('/agents/{id}/edit', function ($id) { $user = require_user_record(); $a = DB::table('agents')->find($id); abort_unless($a,404); $html = '<div class="max-w-2xl card p-6"><h1 class="text-3xl font-black mb-6">تعديل المندوب</h1><form method="POST" action="/agents/'.$a->id.'/edit" class="space-y-4"><input type="hidden" name="_token" value="'.csrf_token().'"><input class="input" name="name" value="'.e($a->name).'" required><input class="input" name="phone" value="'.e($a->phone).'"><input class="input" name="city" value="'.e($a->city).'"><input class="input" name="referral_code" value="'.e($a->referral_code).'" required><label>نسبة المكافأة %</label><input class="input" name="commission_rate" type="number" step="0.001" value="'.e($a->commission_rate).'"><select class="input" name="status"><option value="active" '.($a->status==='active'?'selected':'').'>نشط</option><option value="inactive" '.($a->status==='inactive'?'selected':'').'>موقوف</option></select><button class="btn btn-primary">حفظ</button></form></div>'; return app_layout('تعديل مندوب', $html, $user); });
Route::post('/agents/{id}/edit', function (Request $request, $id) { require_user_record(); DB::table('agents')->where('id',$id)->update(['name'=>$request->name,'phone'=>$request->phone,'city'=>$request->city,'referral_code'=>$request->referral_code,'commission_rate'=>$request->commission_rate,'status'=>$request->status,'updated_at'=>now()]); return redirect('/agents')->with('success','تم تعديل بيانات المندوب.'); });

Route::get('/invoices', function () {
    $user = require_user_record();
    $branches = DB::table('branches')->where('active', true)->get(); $bopts=''; foreach($branches as $b) $bopts.='<option value="'.$b->id.'">'.e($b->name).'</option>';
    $invoices = DB::table('invoices')->join('agents','agents.id','=','invoices.agent_id')->leftJoin('branches','branches.id','=','invoices.branch_id')->leftJoin('users','users.id','=','invoices.user_id')->select('invoices.*','agents.name as agent_name','agents.referral_code','branches.name as branch_name','users.name as user_name')->latest('invoices.id')->limit(80)->get();
    $html = '<h1 class="text-3xl font-black mb-6">الفواتير والنقاط</h1><div class="card p-5 mb-6"><form method="POST" action="/invoices" enctype="multipart/form-data" class="grid md:grid-cols-6 gap-3"><input type="hidden" name="_token" value="'.csrf_token().'"><input class="input" name="invoice_number" placeholder="رقم الفاتورة" required><input class="input" name="agent_query" placeholder="كود/جوال/اسم المندوب" required><input class="input" name="amount" type="number" step="0.01" placeholder="المبلغ قبل الضريبة" required><input class="input" name="discount" type="number" step="0.01" value="0" placeholder="الخصم"><select class="input" name="branch_id"><option value="">الفرع</option>'.$bopts.'</select><input class="input" name="attachment" type="file"><button class="btn btn-primary md:col-span-6">إضافة فاتورة واحتساب النقاط</button></form></div><div class="card overflow-x-auto"><table class="w-full"><tr><th>الفاتورة</th><th>المندوب</th><th>الفرع</th><th>الموظف</th><th>الصافي</th><th>النسبة</th><th>المكافأة</th><th>الحالة</th><th>المرفق</th><th>إجراء</th></tr>';
    foreach($invoices as $i){ $html.='<tr><td>'.e($i->invoice_number).'</td><td>'.e($i->agent_name).' <span class="text-slate-400">'.e($i->referral_code).'</span></td><td>'.e($i->branch_name).'</td><td>'.e($i->user_name).'</td><td>'.money_fmt($i->net_amount).'</td><td>'.$i->reward_rate.'%</td><td>'.money_fmt($i->reward_amount).'</td><td>'.($i->status==='active'?'فعالة':'ملغاة').'</td><td>'.($i->attachment_path?'<a class="text-blue-700" href="/attachments/'.$i->id.'">عرض</a>':'-').'</td><td>'.($i->status==='active'?'<form method="POST" action="/invoices/'.$i->id.'/cancel"><input type="hidden" name="_token" value="'.csrf_token().'"><button class="text-rose-700 font-bold">إلغاء</button></form>':'-').'</td></tr>'; }
    return app_layout('الفواتير', $html.'</table></div>', $user);
});
Route::post('/invoices', function (Request $request) {
    $user = require_user_record();
    $agent = DB::table('agents')->where('status','active')->where(function($q) use($request){$q->where('referral_code',$request->agent_query)->orWhere('phone',$request->agent_query)->orWhere('name','like','%'.$request->agent_query.'%');})->first();
    if(!$agent) return back()->with('error','لم يتم العثور على مندوب نشط بهذا الكود/الجوال/الاسم.');
    $request->validate(['invoice_number'=>'required|unique:invoices,invoice_number','amount'=>'required|numeric|min:0']);
    $amount=(float)$request->amount; $discount=(float)($request->discount ?: 0); $net=eligible_net($amount,$discount); $rate=(float)$agent->commission_rate; $reward=reward_amount($net,$rate);
    $path = null; if($request->hasFile('attachment')) $path = $request->file('attachment')->store('invoices','public');
    DB::table('invoices')->insert(['invoice_number'=>$request->invoice_number,'agent_id'=>$agent->id,'branch_id'=>$request->branch_id ?: $user->branch_id,'user_id'=>$user->id,'amount'=>$amount,'discount'=>$discount,'net_amount'=>$net,'reward_rate'=>$rate,'reward_amount'=>$reward,'status'=>'active','attachment_path'=>$path,'created_at'=>now(),'updated_at'=>now()]);
    return back()->with('success','تمت إضافة الفاتورة واحتساب المكافأة: '.money_fmt($reward));
});
Route::post('/invoices/{id}/cancel', function ($id) { require_user_record(); DB::table('invoices')->where('id',$id)->update(['status'=>'cancelled','cancel_reason'=>'إلغاء من الإدارة','updated_at'=>now()]); return back()->with('success','تم إلغاء الفاتورة وخصم مكافأتها من الرصيد.'); });
Route::get('/attachments/{id}', function($id){ require_user_record(); $i=DB::table('invoices')->find($id); abort_unless($i && $i->attachment_path,404); return response()->file(storage_path('app/public/'.$i->attachment_path)); });

Route::get('/payouts', function(){ $user=require_user_record(); $agents=DB::table('agents')->orderBy('name')->get(); $payouts=DB::table('payouts')->join('agents','agents.id','=','payouts.agent_id')->leftJoin('users','users.id','=','payouts.user_id')->select('payouts.*','agents.name as agent_name','users.name as user_name')->latest('payouts.id')->limit(50)->get(); $html='<h1 class="text-3xl font-black mb-6">الصرف والتصفير</h1><div class="grid lg:grid-cols-2 gap-6"><div class="card p-5"><h2 class="font-black text-xl mb-4">أرصدة المناديب</h2><table class="w-full"><tr><th>المندوب</th><th>المتاح</th><th>يصرف الآن</th><th>الكسر الباقي</th><th></th></tr>'; foreach($agents as $a){$b=agent_balance($a->id);$html.='<tr><td>'.e($a->name).'</td><td>'.money_fmt($b['available']).'</td><td>'.money_fmt($b['payable_now']).'</td><td>'.money_fmt($b['fraction']).'</td><td><form method="POST" action="/payouts"><input type="hidden" name="_token" value="'.csrf_token().'"><input type="hidden" name="agent_id" value="'.$a->id.'"><button class="btn btn-primary">صرف المتاح</button></form></td></tr>'; } $html.='</table></div><div class="card p-5"><h2 class="font-black text-xl mb-4">آخر عمليات الصرف</h2><table class="w-full"><tr><th>المندوب</th><th>المبلغ</th><th>الموظف</th><th>التاريخ</th></tr>'; foreach($payouts as $p){$html.='<tr><td>'.e($p->agent_name).'</td><td>'.money_fmt($p->amount).'</td><td>'.e($p->user_name).'</td><td>'.$p->created_at.'</td></tr>'; } $html.='</table></div></div>'; return app_layout('الصرف', $html, $user); });
Route::post('/payouts', function(Request $request){ $user=require_user_record(); $agent=DB::table('agents')->find($request->agent_id); abort_unless($agent,404); $b=agent_balance($agent->id); $amount=floor($b['available']); if($amount <= 0) return back()->with('error','لا يوجد مبلغ صحيح قابل للصرف. الكسور تبقى للشهر القادم.'); DB::table('payouts')->insert(['agent_id'=>$agent->id,'user_id'=>$user->id,'amount'=>$amount,'method'=>'صرف إداري','note'=>'صرف الرقم الصحيح وترك الكسور','created_at'=>now(),'updated_at'=>now()]); return back()->with('success','تم صرف '.money_fmt($amount).' وبقي الكسر '.money_fmt($b['available']-$amount)); });

Route::get('/agent/login', function(){ $body='<div class="max-w-md mx-auto mt-16 card p-7"><h1 class="text-3xl font-black mb-2">بوابة المندوب</h1><p class="text-slate-500 mb-6">ادخل كود الإحالة أو رقم الجوال لمتابعة رصيدك.</p><form method="POST" action="/agent/login" class="space-y-4"><input type="hidden" name="_token" value="'.csrf_token().'"><input class="input" name="code" placeholder="كود الإحالة أو الجوال" required><button class="btn btn-primary w-full">دخول</button></form><a class="block text-center mt-4 text-slate-500" href="/login">دخول الموظفين</a></div>'; return app_layout('بوابة المندوب',$body); });
Route::post('/agent/login', function(Request $request){ $agent=DB::table('agents')->where('referral_code',$request->code)->orWhere('phone',$request->code)->first(); if(!$agent) return back()->with('error','الكود أو الجوال غير صحيح.'); session(['agent_id'=>$agent->id]); return redirect('/agent/dashboard'); });
Route::get('/agent/dashboard', function(){ $id=session('agent_id'); if(!$id) return redirect('/agent/login'); $a=DB::table('agents')->find($id); $b=agent_balance($id); $invoices=DB::table('invoices')->where('agent_id',$id)->latest()->limit(50)->get(); $html='<div class="flex justify-between mb-6"><div><h1 class="text-3xl font-black">مرحباً '.e($a->name).'</h1><p class="text-slate-500">كودك: '.e($a->referral_code).'</p></div><a class="btn btn-soft" href="/agent/logout">خروج</a></div><div class="grid md:grid-cols-4 gap-4 mb-6"><div class="card p-5"><div class="text-slate-500">الرصيد المتاح</div><div class="text-2xl font-black">'.money_fmt($b['available']).'</div></div><div class="card p-5"><div class="text-slate-500">المصروف</div><div class="text-2xl font-black">'.money_fmt($b['paid']).'</div></div><div class="card p-5"><div class="text-slate-500">الكسر المتبقي</div><div class="text-2xl font-black">'.money_fmt($b['fraction']).'</div></div><div class="card p-5"><div class="text-slate-500">نسبتك</div><div class="text-2xl font-black">'.$a->commission_rate.'%</div></div></div><div class="card overflow-x-auto"><table class="w-full"><tr><th>الفاتورة</th><th>الصافي</th><th>المكافأة</th><th>الحالة</th><th>التاريخ</th></tr>'; foreach($invoices as $i){$html.='<tr><td>'.e($i->invoice_number).'</td><td>'.money_fmt($i->net_amount).'</td><td>'.money_fmt($i->reward_amount).'</td><td>'.($i->status==='active'?'فعالة':'ملغاة').'</td><td>'.$i->created_at.'</td></tr>'; } return app_layout('لوحة المندوب',$html.'</table></div>'); });
Route::get('/agent/logout', function(){ session()->forget('agent_id'); return redirect('/agent/login'); });
