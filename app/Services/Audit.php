<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    public static function record(string $action, Model $model, array $old = [], array $new = []): void
    {
        $exclude = array_flip(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'notes', 'customer_snapshot', 'request_hash', 'idempotency_key']);
        $log = new ActivityLog;
        $log->user_id = auth()->id();
        $log->action = $action;
        $log->model_type = $model->getTable();
        $log->model_id = $model->getKey();
        $log->old_values = array_diff_key($old, $exclude);
        $log->new_values = array_diff_key($new, $exclude);
        $log->ip_address = request()->ip();
        $log->save();
    }
}
