<?php

namespace Modules\MobileApi\Services;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Register;
use App\Models\RegisterHistory;
use Illuminate\Support\Facades\DB;

class RegisterSyncService
{
    public function syncOrderPayment( OrderPayment $orderPayment ): void
    {
        if ( ns()->option->get( 'ns_pos_registers_enabled', 'no' ) !== 'yes' ) {
            return;
        }

        $order = $orderPayment->order;

        if ( ! $order instanceof Order || empty( $order->register_id ) ) {
            return;
        }

        $this->syncRegisterBalance( (int) $order->register_id );
    }

    public function syncRegisterBalanceFromHistory( RegisterHistory $registerHistory ): void
    {
        $this->syncRegisterBalance( (int) $registerHistory->register_id );
    }

    public function syncRegisterBalanceFromRegister( Register $register ): void
    {
        $this->syncRegisterBalance( (int) $register->id );
    }

    public function syncRegisterBalance( int $registerId ): void
    {
        if ( $registerId <= 0 ) {
            return;
        }

        DB::transaction( function () use ( $registerId ) {
            $register = Register::query()
                ->where( 'id', $registerId )
                ->lockForUpdate()
                ->first();

            if ( ! $register instanceof Register ) {
                return;
            }

            $lastOpening = RegisterHistory::query()
                ->where( 'register_id', $register->id )
                ->where( 'action', RegisterHistory::ACTION_OPENING )
                ->orderByDesc( 'id' )
                ->first();

            if ( ! $lastOpening instanceof RegisterHistory ) {
                $register->balance = 0;
                $register->save();

                return;
            }

            if ( $register->status === Register::STATUS_OPENED ) {
                $this->backfillMissingSessionMovements( $register, $lastOpening );
            }

            $computedBalance = $this->rebuildSessionBalances( $register, $lastOpening );

            if ( (float) $register->balance !== (float) $computedBalance ) {
                $register->balance = $computedBalance;
                $register->save();
            }
        } );
    }

    private function rebuildSessionBalances( Register $register, RegisterHistory $lastOpening ): float
    {
        $runningBalance = 0.0;

        $sessionHistory = RegisterHistory::query()
            ->where( 'register_id', $register->id )
            ->where( 'id', '>=', $lastOpening->id )
            ->where( 'created_at', '>=', $lastOpening->created_at )
            ->orderBy( 'created_at' )
            ->orderBy( 'id' )
            ->get();

        foreach ( $sessionHistory as $history ) {
            $balanceBefore = $runningBalance;
            $balanceAfter = $runningBalance;

            if ( in_array( $history->action, RegisterHistory::IN_ACTIONS ) ) {
                $balanceAfter = ns()->currency->define( $balanceBefore )
                    ->additionateBy( $history->value )
                    ->toFloat();
            } elseif ( in_array( $history->action, RegisterHistory::OUT_ACTIONS ) ) {
                $balanceAfter = ns()->currency->define( $balanceBefore )
                    ->subtractBy( $history->value )
                    ->toFloat();
            }

            if (
                (float) $history->balance_before !== (float) $balanceBefore ||
                (float) $history->balance_after !== (float) $balanceAfter
            ) {
                RegisterHistory::query()
                    ->where( 'id', $history->id )
                    ->update( [
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balanceAfter,
                    ] );
            }

            $runningBalance = $balanceAfter;
        }

        return $runningBalance;
    }

    private function backfillMissingSessionMovements( Register $register, RegisterHistory $lastOpening ): void
    {
        $this->backfillMissingOrderPayments( $register, $lastOpening );
        $this->backfillMissingOrderChanges( $register, $lastOpening );
    }

    private function backfillMissingOrderPayments( Register $register, RegisterHistory $lastOpening ): void
    {
        $paymentsTable = ( new OrderPayment )->getTable();
        $ordersTable = ( new Order )->getTable();
        $historyTable = ( new RegisterHistory )->getTable();

        $missingPayments = OrderPayment::query()
            ->select( $paymentsTable . '.*' )
            ->join( $ordersTable, $ordersTable . '.id', '=', $paymentsTable . '.order_id' )
            ->leftJoin( $historyTable, $historyTable . '.payment_id', '=', $paymentsTable . '.id' )
            ->where( $ordersTable . '.register_id', $register->id )
            ->where( $paymentsTable . '.created_at', '>=', $lastOpening->created_at )
            ->whereNull( $historyTable . '.id' )
            ->with( [ 'order', 'type' ] )
            ->orderBy( $paymentsTable . '.id' )
            ->get();

        foreach ( $missingPayments as $payment ) {
            RegisterHistory::withoutEvents( function () use ( $payment ) {
                $registerHistory = new RegisterHistory;
                $registerHistory->register_id = $payment->order->register_id;
                $registerHistory->payment_id = $payment->id;
                $registerHistory->payment_type_id = $payment->type?->id;
                $registerHistory->order_id = $payment->order_id;
                $registerHistory->action = RegisterHistory::ACTION_ORDER_PAYMENT;
                $registerHistory->author = $payment->order->author;
                $registerHistory->balance_before = 0;
                $registerHistory->value = ns()->currency->define( $payment->value )->toFloat();
                $registerHistory->balance_after = 0;
                $registerHistory->created_at = $payment->created_at;
                $registerHistory->updated_at = $payment->updated_at ?? $payment->created_at;
                $registerHistory->save();
            } );
        }
    }

    private function backfillMissingOrderChanges( Register $register, RegisterHistory $lastOpening ): void
    {
        $ordersTable = ( new Order )->getTable();
        $historyTable = ( new RegisterHistory )->getTable();

        $missingChanges = Order::query()
            ->select( $ordersTable . '.*' )
            ->leftJoin( $historyTable, function ( $join ) use ( $ordersTable, $historyTable ) {
                $join->on( $historyTable . '.order_id', '=', $ordersTable . '.id' )
                    ->where( $historyTable . '.action', '=', RegisterHistory::ACTION_ORDER_CHANGE );
            } )
            ->where( $ordersTable . '.register_id', $register->id )
            ->where( $ordersTable . '.payment_status', Order::PAYMENT_PAID )
            ->where( $ordersTable . '.change', '>', 0 )
            ->where( $ordersTable . '.updated_at', '>=', $lastOpening->created_at )
            ->whereNull( $historyTable . '.id' )
            ->orderBy( $ordersTable . '.id' )
            ->get();

        foreach ( $missingChanges as $order ) {
            $changeMoment = $order->final_payment_date ?? $order->updated_at ?? $order->created_at;

            RegisterHistory::withoutEvents( function () use ( $order, $changeMoment ) {
                $registerHistory = new RegisterHistory;
                $registerHistory->payment_type_id = ns()->option->get( 'ns_pos_registers_default_change_payment_type' );
                $registerHistory->register_id = $order->register_id;
                $registerHistory->order_id = $order->id;
                $registerHistory->action = RegisterHistory::ACTION_ORDER_CHANGE;
                $registerHistory->author = $order->author;
                $registerHistory->description = __( 'Change on cash' );
                $registerHistory->balance_before = 0;
                $registerHistory->value = ns()->currency->define( $order->change )->toFloat();
                $registerHistory->balance_after = 0;
                $registerHistory->created_at = $changeMoment;
                $registerHistory->updated_at = $changeMoment;
                $registerHistory->save();
            } );
        }
    }
}
