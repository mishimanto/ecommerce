<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function index()
    {
        $settings = [
            'general' => $this->getGeneralSettings(),
            'store' => $this->getStoreSettings(),
            'payment' => $this->getPaymentSettings(),
            'shipping' => $this->getShippingSettings(),
            'email' => $this->getEmailSettings(),
            'social' => $this->getSocialSettings(),
            'seo' => $this->getSeoSettings(),
            'tax' => $this->getTaxSettings()
        ];

        return response()->json($settings);
    }

    public function update(Request $request)
    {
        $section = $request->input('section');
        $settings = $request->input('settings', []);

        switch ($section) {
            case 'general':
                $this->updateGeneralSettings($settings);
                break;
            case 'store':
                $this->updateStoreSettings($settings);
                break;
            case 'payment':
                $this->updatePaymentSettings($settings);
                break;
            case 'shipping':
                $this->updateShippingSettings($settings);
                break;
            case 'email':
                $this->updateEmailSettings($settings);
                break;
            case 'social':
                $this->updateSocialSettings($settings);
                break;
            case 'seo':
                $this->updateSeoSettings($settings);
                break;
            case 'tax':
                $this->updateTaxSettings($settings);
                break;
            default:
                return response()->json(['message' => 'Invalid section'], 400);
        }

        // Clear settings cache
        Cache::forget('app_settings');

        return response()->json([
            'message' => 'Settings updated successfully'
        ]);
    }

    private function getGeneralSettings()
    {
        return [
            'site_name' => config('app.name', 'Ecommerce Store'),
            'site_url' => config('app.url', 'http://localhost:8000'),
            'timezone' => config('app.timezone', 'UTC'),
            'currency' => config('app.currency', 'USD'),
            'currency_symbol' => config('app.currency_symbol', '$'),
            'date_format' => config('app.date_format', 'Y-m-d'),
            'time_format' => config('app.time_format', 'H:i:s'),
            'default_language' => config('app.locale', 'en'),
            'maintenance_mode' => config('app.maintenance', false),
            'maintenance_message' => config('app.maintenance_message', 'Site is under maintenance'),
            'logo' => config('app.logo', null),
            'favicon' => config('app.favicon', null),
            'admin_email' => config('app.admin_email', 'admin@example.com')
        ];
    }

    private function getStoreSettings()
    {
        return [
            'store_name' => config('store.name', 'Ecommerce Store'),
            'store_address' => config('store.address', ''),
            'store_phone' => config('store.phone', ''),
            'store_email' => config('store.email', ''),
            'store_country' => config('store.country', ''),
            'store_city' => config('store.city', ''),
            'store_postal_code' => config('store.postal_code', ''),
            'store_order_prefix' => config('store.order_prefix', 'ORD'),
            'store_invoice_prefix' => config('store.invoice_prefix', 'INV'),
            'store_weight_unit' => config('store.weight_unit', 'kg'),
            'store_dimension_unit' => config('store.dimension_unit', 'cm'),
            'store_terms_conditions' => config('store.terms_conditions', ''),
            'store_privacy_policy' => config('store.privacy_policy', ''),
            'store_return_policy' => config('store.return_policy', ''),
            'store_shipping_policy' => config('store.shipping_policy', '')
        ];
    }

    private function getPaymentSettings()
    {
        return [
            'stripe_enabled' => config('payment.stripe.enabled', false),
            'stripe_publishable_key' => config('payment.stripe.publishable_key', ''),
            'stripe_secret_key' => config('payment.stripe.secret_key', ''),
            'sslcommerz_enabled' => config('payment.sslcommerz.enabled', false),
            'sslcommerz_store_id' => config('payment.sslcommerz.store_id', ''),
            'sslcommerz_store_password' => config('payment.sslcommerz.store_password', ''),
            'cod_enabled' => config('payment.cod.enabled', true),
            'bank_transfer_enabled' => config('payment.bank_transfer.enabled', false),
            'bank_transfer_details' => config('payment.bank_transfer.details', ''),
            'default_payment_method' => config('payment.default', 'cod'),
            'test_mode' => config('payment.test_mode', true),
            'currency' => config('payment.currency', 'USD'),
            'minimum_order_amount' => config('payment.minimum_order_amount', 0)
        ];
    }

    private function getShippingSettings()
    {
        return [
            'flat_rate_enabled' => config('shipping.flat_rate.enabled', true),
            'flat_rate_cost' => config('shipping.flat_rate.cost', 10),
            'free_shipping_enabled' => config('shipping.free_shipping.enabled', false),
            'free_shipping_min_amount' => config('shipping.free_shipping.min_amount', 100),
            'local_pickup_enabled' => config('shipping.local_pickup.enabled', false),
            'local_pickup_cost' => config('shipping.local_pickup.cost', 0),
            'local_pickup_address' => config('shipping.local_pickup.address', ''),
            'default_shipping_method' => config('shipping.default', 'flat_rate'),
            'shipping_calculation' => config('shipping.calculation', 'flat'),
            'shipping_tax' => config('shipping.tax', 0),
            'estimated_delivery_time' => config('shipping.estimated_delivery', '3-5 business days')
        ];
    }

    private function getEmailSettings()
    {
        return [
            'mail_driver' => config('mail.default', 'smtp'),
            'mail_host' => config('mail.mailers.smtp.host', ''),
            'mail_port' => config('mail.mailers.smtp.port', ''),
            'mail_username' => config('mail.mailers.smtp.username', ''),
            'mail_password' => config('mail.mailers.smtp.password', ''),
            'mail_encryption' => config('mail.mailers.smtp.encryption', ''),
            'mail_from_address' => config('mail.from.address', ''),
            'mail_from_name' => config('mail.from.name', ''),
            'order_confirmation_enabled' => config('email.order_confirmation', true),
            'order_status_enabled' => config('email.order_status', true),
            'shipping_confirmation_enabled' => config('email.shipping_confirmation', true),
            'delivery_confirmation_enabled' => config('email.delivery_confirmation', true),
            'customer_welcome_enabled' => config('email.customer_welcome', true),
            'password_reset_enabled' => config('email.password_reset', true)
        ];
    }

    private function getSocialSettings()
    {
        return [
            'facebook_enabled' => config('social.facebook.enabled', false),
            'facebook_app_id' => config('social.facebook.app_id', ''),
            'facebook_app_secret' => config('social.facebook.app_secret', ''),
            'google_enabled' => config('social.google.enabled', false),
            'google_client_id' => config('social.google.client_id', ''),
            'google_client_secret' => config('social.google.client_secret', ''),
            'twitter_enabled' => config('social.twitter.enabled', false),
            'twitter_api_key' => config('social.twitter.api_key', ''),
            'twitter_api_secret' => config('social.twitter.api_secret', ''),
            'instagram_enabled' => config('social.instagram.enabled', false),
            'instagram_client_id' => config('social.instagram.client_id', ''),
            'instagram_client_secret' => config('social.instagram.client_secret', ''),
            'social_login_enabled' => config('social.login_enabled', false)
        ];
    }

    private function getSeoSettings()
    {
        return [
            'meta_title' => config('seo.meta_title', ''),
            'meta_description' => config('seo.meta_description', ''),
            'meta_keywords' => config('seo.meta_keywords', ''),
            'google_analytics_enabled' => config('seo.google_analytics.enabled', false),
            'google_analytics_id' => config('seo.google_analytics.id', ''),
            'google_site_verification' => config('seo.google_site_verification', ''),
            'bing_site_verification' => config('seo.bing_site_verification', ''),
            'robots_txt' => config('seo.robots_txt', ''),
            'sitemap_enabled' => config('seo.sitemap.enabled', true),
            'sitemap_frequency' => config('seo.sitemap.frequency', 'daily'),
            'sitemap_priority' => config('seo.sitemap.priority', 0.8)
        ];
    }

    private function getTaxSettings()
    {
        return [
            'tax_enabled' => config('tax.enabled', true),
            'tax_inclusive' => config('tax.inclusive', false),
            'default_tax_rate' => config('tax.default_rate', 5),
            'tax_rates' => config('tax.rates', []),
            'tax_by_location' => config('tax.by_location', false),
            'tax_shipping' => config('tax.shipping', false),
            'tax_classes' => config('tax.classes', [])
        ];
    }

    private function updateGeneralSettings($settings)
    {
        $this->updateEnv([
            'APP_NAME' => $settings['site_name'] ?? config('app.name'),
            'APP_URL' => $settings['site_url'] ?? config('app.url'),
            'APP_TIMEZONE' => $settings['timezone'] ?? config('app.timezone'),
            'APP_LOCALE' => $settings['default_language'] ?? config('app.locale'),
            'APP_CURRENCY' => $settings['currency'] ?? 'USD',
            'APP_CURRENCY_SYMBOL' => $settings['currency_symbol'] ?? '$'
        ]);

        // Handle file uploads
        if (isset($settings['logo']) && is_string($settings['logo'])) {
            $this->handleFileUpload($settings['logo'], 'logo');
        }
        if (isset($settings['favicon']) && is_string($settings['favicon'])) {
            $this->handleFileUpload($settings['favicon'], 'favicon');
        }
    }

    private function updateStoreSettings($settings)
    {
        $this->updateConfig('store', $settings);
    }

    private function updatePaymentSettings($settings)
    {
        $this->updateEnv([
            'STRIPE_ENABLED' => $settings['stripe_enabled'] ? 'true' : 'false',
            'STRIPE_PUBLISHABLE_KEY' => $settings['stripe_publishable_key'] ?? '',
            'STRIPE_SECRET_KEY' => $settings['stripe_secret_key'] ?? '',
            'SSLCOMMERZ_ENABLED' => $settings['sslcommerz_enabled'] ? 'true' : 'false',
            'SSLCOMMERZ_STORE_ID' => $settings['sslcommerz_store_id'] ?? '',
            'SSLCOMMERZ_STORE_PASSWORD' => $settings['sslcommerz_store_password'] ?? ''
        ]);

        $this->updateConfig('payment', $settings);
    }

    private function updateShippingSettings($settings)
    {
        $this->updateConfig('shipping', $settings);
    }

    private function updateEmailSettings($settings)
    {
        $this->updateEnv([
            'MAIL_MAILER' => $settings['mail_driver'] ?? 'smtp',
            'MAIL_HOST' => $settings['mail_host'] ?? '',
            'MAIL_PORT' => $settings['mail_port'] ?? '',
            'MAIL_USERNAME' => $settings['mail_username'] ?? '',
            'MAIL_PASSWORD' => $settings['mail_password'] ?? '',
            'MAIL_ENCRYPTION' => $settings['mail_encryption'] ?? '',
            'MAIL_FROM_ADDRESS' => $settings['mail_from_address'] ?? '',
            'MAIL_FROM_NAME' => $settings['mail_from_name'] ?? ''
        ]);

        $this->updateConfig('email', $settings);
    }

    private function updateSocialSettings($settings)
    {
        $this->updateEnv([
            'FACEBOOK_ENABLED' => $settings['facebook_enabled'] ? 'true' : 'false',
            'FACEBOOK_APP_ID' => $settings['facebook_app_id'] ?? '',
            'FACEBOOK_APP_SECRET' => $settings['facebook_app_secret'] ?? '',
            'GOOGLE_ENABLED' => $settings['google_enabled'] ? 'true' : 'false',
            'GOOGLE_CLIENT_ID' => $settings['google_client_id'] ?? '',
            'GOOGLE_CLIENT_SECRET' => $settings['google_client_secret'] ?? ''
        ]);

        $this->updateConfig('social', $settings);
    }

    private function updateSeoSettings($settings)
    {
        $this->updateConfig('seo', $settings);
    }

    private function updateTaxSettings($settings)
    {
        $this->updateConfig('tax', $settings);
    }

    private function updateEnv($data)
    {
        $envPath = base_path('.env');
        
        if (file_exists($envPath)) {
            $envContent = file_get_contents($envPath);
            
            foreach ($data as $key => $value) {
                $pattern = "/^{$key}=.*/m";
                if (preg_match($pattern, $envContent)) {
                    $envContent = preg_replace($pattern, "{$key}={$value}", $envContent);
                } else {
                    $envContent .= "\n{$key}={$value}";
                }
            }
            
            file_put_contents($envPath, $envContent);
        }
    }

    private function updateConfig($key, $data)
    {
        $configPath = config_path("{$key}.php");
        
        if (!file_exists($configPath)) {
            // Create config file if it doesn't exist
            $configContent = "<?php\n\nreturn " . var_export($data, true) . ";\n";
            file_put_contents($configPath, $configContent);
        } else {
            // Merge with existing config
            $existingConfig = include $configPath;
            $newConfig = array_merge($existingConfig, $data);
            $configContent = "<?php\n\nreturn " . var_export($newConfig, true) . ";\n";
            file_put_contents($configPath, $configContent);
        }
    }

    private function handleFileUpload($fileData, $type)
    {
        if (strpos($fileData, 'data:image') === 0) {
            // Handle base64 image
            list($type, $fileData) = explode(';', $fileData);
            list(, $fileData) = explode(',', $fileData);
            $fileData = base64_decode($fileData);
            
            $fileName = $type . '.' . explode('/', $type)[1];
            Storage::disk('public')->put('settings/' . $fileName, $fileData);
            
            $this->updateEnv([
                strtoupper($type) => 'storage/settings/' . $fileName
            ]);
        }
    }

    public function uploadFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,gif,ico|max:2048',
            'type' => 'required|in:logo,favicon'
        ]);

        $file = $request->file('file');
        $type = $request->input('type');
        $fileName = $type . '.' . $file->getClientOriginalExtension();
        
        $path = $file->storeAs('settings', $fileName, 'public');

        return response()->json([
            'url' => Storage::url($path),
            'path' => $path,
            'message' => 'File uploaded successfully'
        ]);
    }

    public function clearCache()
    {
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');

        return response()->json([
            'message' => 'Cache cleared successfully'
        ]);
    }

    public function getBackupList()
    {
        $backups = [];
        $backupPath = storage_path('app/backups');
        
        if (file_exists($backupPath)) {
            $files = scandir($backupPath);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $backups[] = [
                        'name' => $file,
                        'size' => filesize($backupPath . '/' . $file),
                        'created_at' => date('Y-m-d H:i:s', filemtime($backupPath . '/' . $file))
                    ];
                }
            }
        }

        return response()->json($backups);
    }

    public function createBackup()
    {
        Artisan::call('backup:run', ['--only-db' => true]);

        return response()->json([
            'message' => 'Backup created successfully'
        ]);
    }
}