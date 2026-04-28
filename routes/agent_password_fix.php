<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::get('/agent/login', function () {
    $body = '<div class="max-w-md mx-auto mt-16 card p-7"><h1 class="text-3xl font-black mb-2">بوابة المندوب</h1><p class="text-slate-500 mb-6">ادخل كود الإحالة أو رقم الجوال مع الرقم السري لعرض الرصيد.</p><form method="POST" action="/agent/login" class="space-y-4"><input type="hidden" name="_token" value="'.csrf_token().'"><input class="input" name="code" placeholder="كود الإحالة أو الجوال" required><input class="input" name="password" type="password" placeholder="الرقم السري" required><button class="btn btn-primary w-full">دخول</button></form><a class="block text-center mt-4 text-slate-500" href="/login">دخول الموظفين</a></div>';
    return admin_ui('بوابة المندوب', $body);
});

Route::post('/agent/login', function (Request $request) {
    $request->validate(['code' => 'required', 'password' => 'required']);

    $agent = DB::table('agents')
        ->where('referral_code', $request->code)
        ->orWhere('phone', $request->code)
        ->first();

    if (!$agent) {
        return back()->with('error', 'الكود أو الجوال أو الرقم السري غير صحيح.');
    }

    if (empty($agent->portal_password)) {
        return back()->with('error', 'لم يتم تفعيل رقم سري لهذا المندوب. تواصل مع الإدارة.');
    }

    if (!Hash::check($request->password, $agent->portal_password)) {
        return back()->with('error', 'الكود أو الجوال أو الرقم السري غير صحيح.');
    }

    session(['agent_id' => $agent->id]);
    return redirect('/agent/dashboard');
});

Route::get('/agents/{id}/edit', function ($id) {
    $user = require_user_record();
    $a = DB::table('agents')->find($id);
    abort_unless($a, 404);

    $html = '<div class="max-w-2xl card p-6"><h1 class="text-3xl font-black mb-6">تعديل المندوب</h1><form method="POST" action="/agents/'.$a->id.'/edit" class="space-y-4"><input type="hidden" name="_token" value="'.csrf_token().'"><label>اسم المندوب</label><input class="input" name="name" value="'.e($a->name).'" required><label>الجوال</label><input class="input" name="phone" value="'.e($a->phone).'"><label>المدينة</label><input class="input" name="city" value="'.e($a->city).'"><label>كود الإحالة</label><input class="input" name="referral_code" value="'.e($a->referral_code).'" required><label>الرقم السري لبوابة المندوب</label><input class="input" name="portal_password" type="password" placeholder="اكتب رقم سري جديد أو اتركه بدون تغيير"><p class="text-slate-500 text-sm">الرقم السري لا يظهر بعد الحفظ، فقط يتم تغييره إذا كتبت رقم جديد.</p><label>نسبة المكافأة %</label><input class="input" name="commission_rate" type="number" step="0.001" value="'.e($a->commission_rate).'"><label>الحالة</label><select class="input" name="status"><option value="active" '.($a->status==='active'?'selected':'').'>نشط</option><option value="inactive" '.($a->status==='inactive'?'selected':'').'>موقوف</option></select><button class="btn btn-primary">حفظ</button></form></div>';
    return admin_ui('تعديل مندوب', $html, $user);
});

Route::post('/agents/{id}/edit', function (Request $request, $id) {
    require_user_record();

    $data = [
        'name' => $request->name,
        'phone' => $request->phone,
        'city' => $request->city,
        'referral_code' => $request->referral_code,
        'commission_rate' => $request->commission_rate,
        'status' => $request->status,
        'updated_at' => now(),
    ];

    if ($request->filled('portal_password') && Schema::hasColumn('agents', 'portal_password')) {
        $data['portal_password'] = Hash::make($request->portal_password);
    }

    DB::table('agents')->where('id', $id)->update($data);
    return redirect('/agents')->with('success', 'تم تعديل بيانات المندوب.');
});

Route::post('/agents', function (Request $request) {
    require_user_record();
    $request->validate(['referral_code' => 'required|unique:agents,referral_code']);

    $data = [
        'name' => $request->name,
        'phone' => $request->phone,
        'city' => $request->city,
        'referral_code' => $request->referral_code,
        'commission_rate' => $request->commission_rate ?: 0.5,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ];

    if ($request->filled('portal_password') && Schema::hasColumn('agents', 'portal_password')) {
        $data['portal_password'] = Hash::make($request->portal_password);
    }

    DB::table('agents')->insert($data);
    return back()->with('success', 'تمت إضافة المندوب.');
});
