<?php

namespace App\Http\Controllers;

use App\Models\About;
use App\Models\Guide;
use App\Models\Plan;
use App\Models\PrivacyPolicy;
use App\Models\SaasFeature;
use App\Models\Setting;
use App\Models\Term;
use Illuminate\Http\Request;

class Home extends Controller
{
    public function index()
    {
        $settings = Setting::getByType('general');
        $plans = Plan::where('is_active', 1)
            ->where('price', '>', 0)
            ->get();
        $feature = SaasFeature::all();
        $featureCount = $feature->count();
        $about = About::first();
        $ReCaptcha = Setting::getByType('recaptcha');
        $freeplan = Plan::where('slug', 'free-trial')->first();
        return view('home.index', compact('settings', 'plans', 'feature', 'featureCount', 'about', 'ReCaptcha', 'freeplan'));
    }
    public function privacy()
    {
        $ReCaptcha = Setting::getByType('recaptcha');
        $policy = PrivacyPolicy::first();
        return view('home.privacy_policy', compact('policy', 'ReCaptcha'));
    }

    public function terms()
    {
        $terms = Term::first();
        $ReCaptcha = Setting::getByType('recaptcha');
        return view('home.terms', compact('terms', 'ReCaptcha'));
    }

    public function guide()
    {
        $ReCaptcha = Setting::getByType('recaptcha');
        $guide = Guide::first();
        return view('home.guide', compact('guide', 'ReCaptcha'));
    }
}
