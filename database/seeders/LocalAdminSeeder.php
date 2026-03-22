<?php

namespace Database\Seeders;

use App\BusinessLocation;
use App\Contact;
use App\Currency;
use App\InvoiceLayout;
use App\InvoiceScheme;
use App\NotificationTemplate;
use App\Unit;
use App\User;
use App\Utils\BusinessUtil;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class LocalAdminSeeder extends Seeder
{
    public function run()
    {
        DB::transaction(function () {
            $this->seedBaseData();

            $username = 'admin';
            $password = '123456';

            $existingUser = User::where('username', $username)->first();
            if ($existingUser && ! empty($existingUser->business_id)) {
                $existingUser->password = Hash::make($password);
                $existingUser->allow_login = 1;
                $existingUser->status = 'active';
                $existingUser->user_type = $existingUser->user_type ?: 'user';
                $existingUser->save();

                $this->command?->info("Admin user already existed. Password reset for username: {$username}");

                return;
            }

            $currencyId = Currency::query()->value('id');

            $owner = $existingUser ?: User::create([
                'surname' => 'Mr.',
                'first_name' => 'Admin',
                'last_name' => 'User',
                'username' => $username,
                'email' => 'admin@example.com',
                'password' => Hash::make($password),
                'language' => 'en',
                'status' => 'active',
                'allow_login' => 1,
                'user_type' => 'user',
            ]);

            $businessUtil = app(BusinessUtil::class);
            $business = $businessUtil->createNewBusiness([
                'name' => 'Sale Pro',
                'currency_id' => $currencyId,
                'start_date' => now()->toDateString(),
                'tax_label_1' => 'TIN',
                'tax_number_1' => 'N/A',
                'tax_label_2' => null,
                'tax_number_2' => null,
                'time_zone' => config('app.timezone', 'Asia/Phnom_Penh'),
                'fy_start_month' => 1,
                'accounting_method' => 'fifo',
                'owner_id' => $owner->id,
                'enabled_modules' => ['purchases', 'add_sale', 'pos_sale', 'stock_transfers', 'stock_adjustment', 'expenses'],
            ]);

            $owner->business_id = $business->id;
            $owner->status = 'active';
            $owner->allow_login = 1;
            $owner->user_type = 'user';
            $owner->save();

            $adminRole = Role::query()->firstOrCreate(
                ['name' => 'Admin#'.$business->id],
                ['business_id' => $business->id, 'guard_name' => 'web', 'is_default' => 1]
            );
            $owner->syncRoles([$adminRole->name]);

            $cashierRole = Role::query()->firstOrCreate(
                ['name' => 'Cashier#'.$business->id],
                ['business_id' => $business->id, 'guard_name' => 'web', 'is_default' => 0]
            );
            $cashierRole->syncPermissions([
                'sell.view',
                'sell.create',
                'sell.update',
                'sell.delete',
                'access_all_locations',
                'view_cash_register',
                'close_cash_register',
            ]);

            $invoiceScheme = InvoiceScheme::query()->create([
                'name' => 'Default',
                'scheme_type' => 'blank',
                'prefix' => '',
                'start_number' => 1,
                'invoice_count' => 0,
                'total_digits' => 4,
                'is_default' => 1,
                'business_id' => $business->id,
            ]);

            $invoiceLayout = InvoiceLayout::query()->create([
                'name' => 'Default',
                'header_text' => null,
                'invoice_no_prefix' => 'Invoice No.',
                'invoice_heading' => 'Invoice',
                'sub_total_label' => 'Subtotal',
                'discount_label' => 'Discount',
                'tax_label' => 'Tax',
                'total_label' => 'Total',
                'show_landmark' => 1,
                'show_city' => 1,
                'show_state' => 1,
                'show_zip_code' => 1,
                'show_country' => 1,
                'highlight_color' => '#000000',
                'footer_text' => '',
                'is_default' => 1,
                'business_id' => $business->id,
            ]);

            Unit::query()->firstOrCreate([
                'business_id' => $business->id,
                'actual_name' => 'Pieces',
            ], [
                'short_name' => 'Pc(s)',
                'allow_decimal' => 0,
                'created_by' => $owner->id,
            ]);

            Contact::query()->firstOrCreate([
                'business_id' => $business->id,
                'type' => 'customer',
                'name' => 'Walk-In Customer',
                'is_default' => 1,
            ], [
                'mobile' => '',
                'created_by' => $owner->id,
                'contact_id' => 'CO0001',
                'credit_limit' => 0,
            ]);

            $paymentAccounts = [
                'cash' => ['is_enabled' => 1, 'account' => null],
                'card' => ['is_enabled' => 1, 'account' => null],
                'cheque' => ['is_enabled' => 1, 'account' => null],
                'bank_transfer' => ['is_enabled' => 1, 'account' => null],
                'other' => ['is_enabled' => 1, 'account' => null],
            ];

            $location = BusinessLocation::query()->create([
                'business_id' => $business->id,
                'name' => 'Main Store',
                'landmark' => 'Main Office',
                'city' => 'Phnom Penh',
                'state' => 'Phnom Penh',
                'zip_code' => '12000',
                'country' => 'Cambodia',
                'invoice_scheme_id' => $invoiceScheme->id,
                'sale_invoice_scheme_id' => $invoiceScheme->id,
                'invoice_layout_id' => $invoiceLayout->id,
                'sale_invoice_layout_id' => $invoiceLayout->id,
                'mobile' => '',
                'alternate_number' => '',
                'email' => '',
                'website' => '',
                'location_id' => 'BL0001',
                'default_payment_accounts' => json_encode($paymentAccounts),
                'is_active' => 1,
            ]);

            DB::table('reference_counts')->insert([
                [
                    'ref_type' => 'contacts',
                    'ref_count' => 1,
                    'business_id' => $business->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'ref_type' => 'business_location',
                    'ref_count' => 1,
                    'business_id' => $business->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            foreach (NotificationTemplate::defaultNotificationTemplates($business->id) as $template) {
                NotificationTemplate::query()->create($template);
            }

            Permission::firstOrCreate(
                ['name' => 'location.'.$location->id],
                ['guard_name' => 'web']
            );

            $this->command?->info("Created admin login: {$username} / {$password}");
        });
    }

    protected function seedBaseData(): void
    {
        $requiredPermissions = [
            'sell.view',
            'sell.create',
            'sell.update',
            'sell.delete',
            'access_all_locations',
            'view_cash_register',
            'close_cash_register',
            'dashboard.data',
        ];

        if (! DB::table('permissions')->where('name', 'sell.view')->exists()) {
            foreach ($requiredPermissions as $permissionName) {
                Permission::query()->firstOrCreate(
                    ['name' => $permissionName],
                    ['guard_name' => 'web']
                );
            }
        }

        if (! DB::table('currencies')->exists()) {
            $this->call(CurrenciesTableSeeder::class);
        }

        if (! DB::table('barcodes')->exists()) {
            $this->call(BarcodesTableSeeder::class);
        }
    }
}
