<?php
namespace App;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionSupplierCashRingDetail extends Model
{
    protected $table = 'transactions_supplier_cash_ring_detail';
    
    // If you don't want Laravel to handle timestamps, set this to false
    public $timestamps = false;
    
    // Define guarded attributes that are not mass assignable
    protected $guarded = ['id'];
    
    /**
     * Get the product associated with this detail
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
    
    /**
     * Get the cash ring balance associated with this detail
     */
    public function cashRingBalance()
    {
        return $this->belongsTo(CashRingBalance::class, 'cash_ring_balance_id', 'id');
    }
    
    /**
     * Get the main transaction
     */
    public function transaction()
    {
        return $this->belongsTo(TransactionSupplierCashRing::class, 'transactions_supplier_cash_ring_id', 'id');
    }
}