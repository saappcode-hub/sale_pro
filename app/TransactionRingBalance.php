<?php

// File: app/TransactionRingBalance.php
namespace App;

use Illuminate\Database\Eloquent\Model;

class TransactionRingBalance extends Model
{
    protected $table = 'transactions_ring_balance';
    public $timestamps = false;
    protected $guarded = ['id'];

    public function transactionSellRingBalances()
    {
        return $this->hasMany(TransactionSellRingBalance::class, 'transactions_ring_balance_id', 'id');
    }

    public function business()
    {
        return $this->hasOne(Business::class, 'id', 'business_id');
    }

    public function location()
    {
        return $this->hasOne(BusinessLocation::class, 'id', 'location_id');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function sales_person()
    {
        return $this->hasOne(User::class, 'id', 'created_by');
    }
}

// File: app/TransactionSellRingBalance.php
namespace App;

use Illuminate\Database\Eloquent\Model;

class TransactionSellRingBalance extends Model
{
    protected $table = 'transaction_sell_ring_balance';
    public $timestamps = false;
    protected $guarded = ['id'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function transactionRingBalance()
    {
        return $this->belongsTo(TransactionRingBalance::class, 'transactions_ring_balance_id');
    }

    // FIXED: This should match the relationship name used in the controller
    public function ringUnits()
    {
        return $this->hasMany(TransactionSellRingBalanceRingUnits::class, 'transaction_sell_ring_balance_id');
    }

    // FIXED: This should match the relationship name used in the controller  
    public function cashRings()
    {
        return $this->hasMany(TransactionCashRingBalance::class, 'transaction_sell_ring_balance_id');
    }

    // Alternative relationship names for the show method
    public function cashRingBalanceDetails()
    {
        return $this->hasMany(TransactionCashRingBalance::class, 'transaction_sell_ring_balance_id', 'id');
    }

    public function ringUnitDetails()
    {
        return $this->hasMany(TransactionSellRingBalanceRingUnits::class, 'transaction_sell_ring_balance_id', 'id');
    }
}

// File: app/TransactionSellRingBalanceRingUnits.php
namespace App;

use Illuminate\Database\Eloquent\Model;

class TransactionSellRingBalanceRingUnits extends Model
{
    protected $table = 'transaction_sell_ring_balance_ring_units';
    public $timestamps = false;
    protected $guarded = ['id'];

    public function transactionSellRingBalance()
    {
        return $this->belongsTo(TransactionSellRingBalance::class, 'transaction_sell_ring_balance_id');
    }

    public function ringUnit()
    {
        return $this->belongsTo(RingUnit::class, 'ring_units_id');
    }
}

// File: app/TransactionCashRingBalance.php
namespace App;

use Illuminate\Database\Eloquent\Model;

class TransactionCashRingBalance extends Model
{
    protected $table = 'transaction_cash_ring_balance';
    protected $guarded = ['id'];
    public $timestamps = false;

    public function cashRingBalance()
    {
        return $this->belongsTo(CashRingBalance::class, 'cash_ring_balance_id');
    }

    public function transactionSellRingBalance()
    {
        return $this->belongsTo(TransactionSellRingBalance::class, 'transaction_sell_ring_balance_id');
    }
}

// File: app/CashRingBalance.php
namespace App;

use Illuminate\Database\Eloquent\Model;

class CashRingBalance extends Model
{
    protected $table = 'cash_ring_balance';
    protected $guarded = ['id'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}

// File: app/RingUnit.php
namespace App;

use Illuminate\Database\Eloquent\Model;

class RingUnit extends Model
{
    protected $table = 'ring_unit';
    protected $guarded = ['id'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
