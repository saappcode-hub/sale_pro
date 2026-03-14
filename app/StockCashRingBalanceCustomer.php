<?php
namespace App;
use Illuminate\Database\Eloquent\Model;

class StockCashRingBalanceCustomer extends Model
{
    protected $table = 'stock_cash_ring_balance_customer';
    public $timestamps = false;
    public $incrementing = false; // No auto-increment behavior
    protected $primaryKey = ['contact_id', 'business_id']; // Composite primary key
    protected $keyType = 'string'; // Since we have composite key
    protected $fillable = ['contact_id', 'business_id', 'total_cuurency_dollar'];

    /**
     * Set the keys for a save update query.
     */
    protected function setKeysForSaveQuery($query)
    {
        $keys = $this->getKeyName();
        if(!is_array($keys)){
            return parent::setKeysForSaveQuery($query);
        }

        foreach($keys as $keyName){
            $query->where($keyName, '=', $this->getKeyForSaveQuery($keyName));
        }

        return $query;
    }

    /**
     * Get the primary key value for a save query.
     */
    protected function getKeyForSaveQuery($keyName = null)
    {
        return $this->original[$keyName] ?? $this->getAttribute($keyName);
    }
}