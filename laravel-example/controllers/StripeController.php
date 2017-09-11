<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller,
    Illuminate\Http\Request;

class StripeController extends Controller {
    // === Public Methods === //

    /**
     * Display Stripe settings form.
     * @param Request $request
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function show(Request $request) {
        if (!app('payments.stripe')->isShowSettings()) {
            return redirect('/admin');
        }
        return $this->getFormView();
    }

    /**
     * Save Stripe page settings and redirect back to the form.
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function update(Request $request) {
        $validator = \Validator::make($request->all(), [
            'is_enabled' => 'boolean',
            'secret_key' => 'required_with:is_enabled',
            'publishable_key' => 'required_with:is_enabled',
        ]);
        $validator->sometimes('secret_key', 'stripe_secret_key', function() use ($request) {
            return $request->has('is_enabled');
        });
        $this->validateWith($validator, $request);
        app('payments.stripe')->setIsEnabled($request->has('is_enabled'));
        \AdminDAO::setStripeSecretKey($request->get('secret_key'));
        \AdminDAO::setStripePublishableKey($request->get('publishable_key'));
        return $this->getFormView()->with('saved', true);
    }

    // === Protected Methods === //

    /**
     * Returns Stripe settings form view.
     * @return \Illuminate\View\View
     */
    protected function getFormView() {
        app()->instance('top-nav.current', 'settings');
        return view('standard.admin.settings.payments.stripe', [
            'user' => \AdminSession::load()->getUser(),
            'isEnabled' => app('payments.stripe')->isEnabledInSettings(),
            'secretKey' => \AdminDAO::getStripeSecretKey(),
            'publishableKey' => \AdminDAO::getStripePublishableKey(),
        ]);
    }
}
