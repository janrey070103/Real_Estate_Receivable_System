<?php
/**
 * Financial Helper Functions
 * Real Estate Receivable System
 * 
 * Functions for calculating interest, amortization, and penalties
 */

/**
 * Calculate monthly amortization using the standard formula
 * Formula: M = P * [r(1+r)^n] / [(1+r)^n - 1]
 * 
 * @param float $principal Loan principal (full total price — security deposit is separate)
 * @param float $annual_rate Annual interest rate as percentage (e.g., 12 for 12%)
 * @param int $term_months Number of months
 * @return array ['monthly_payment', 'total_payment', 'total_interest']
 */
function calculate_amortization($principal, $annual_rate, $term_months)
{
    // Handle zero interest case
    if ($annual_rate <= 0) {
        $monthly_payment = $principal / $term_months;
        return [
            'monthly_payment' => round($monthly_payment, 2),
            'total_payment' => round($principal, 2),
            'total_interest' => 0.00
        ];
    }

    // Convert annual rate to monthly decimal rate
    $monthly_rate = ($annual_rate / 100) / 12;

    // Calculate monthly payment using amortization formula
    $numerator = $monthly_rate * pow(1 + $monthly_rate, $term_months);
    $denominator = pow(1 + $monthly_rate, $term_months) - 1;

    $monthly_payment = $principal * ($numerator / $denominator);
    $total_payment = $monthly_payment * $term_months;
    $total_interest = $total_payment - $principal;

    return [
        'monthly_payment' => round($monthly_payment, 2),
        'total_payment' => round($total_payment, 2),
        'total_interest' => round($total_interest, 2)
    ];
}

/**
 * Generate a complete amortization schedule with principal and interest breakdown
 * 
 * @param float $principal Loan principal
 * @param float $annual_rate Annual interest rate as percentage
 * @param int $term_months Number of months
 * @param DateTime $start_date Start date for the schedule
 * @return array Array of schedule entries with due_date, payment, principal, interest, balance
 */
function generate_amortization_schedule($principal, $annual_rate, $term_months, $start_date)
{
    $schedule = [];
    $balance = $principal;

    // Handle zero interest case
    if ($annual_rate <= 0) {
        $monthly_payment = round($principal / $term_months, 2);
        $total_allocated = 0;

        for ($month = 1; $month <= $term_months; $month++) {
            $due_date = clone $start_date;
            $due_date->modify("+{$month} month");

            // Last month adjustment for rounding
            if ($month === $term_months) {
                $monthly_payment = $principal - $total_allocated;
            }

            $balance -= $monthly_payment;
            $total_allocated += $monthly_payment;

            $schedule[] = [
                'schedule_number' => $month,
                'due_date' => $due_date->format('Y-m-d'),
                'amount_due' => $monthly_payment,
                'principal_amount' => $monthly_payment,
                'interest_amount' => 0.00,
                'remaining_balance' => max(0, round($balance, 2))
            ];
        }

        return $schedule;
    }

    // Calculate monthly payment with interest
    $monthly_rate = ($annual_rate / 100) / 12;
    $amort = calculate_amortization($principal, $annual_rate, $term_months);
    $monthly_payment = $amort['monthly_payment'];

    for ($month = 1; $month <= $term_months; $month++) {
        $due_date = clone $start_date;
        $due_date->modify("+{$month} month");

        // Calculate interest for this period
        $interest_amount = round($balance * $monthly_rate, 2);
        $principal_amount = $monthly_payment - $interest_amount;

        // Last month adjustment for rounding
        if ($month === $term_months) {
            $principal_amount = $balance;
            $monthly_payment = $principal_amount + $interest_amount;
        }

        $balance -= $principal_amount;

        $schedule[] = [
            'schedule_number' => $month,
            'due_date' => $due_date->format('Y-m-d'),
            'amount_due' => round($monthly_payment, 2),
            'principal_amount' => round($principal_amount, 2),
            'interest_amount' => $interest_amount,
            'remaining_balance' => max(0, round($balance, 2))
        ];
    }

    return $schedule;
}

/**
 * Calculate late payment penalty
 * 
 * @param float $amount_due Original amount due
 * @param int $days_overdue Number of days past due date
 * @param float $penalty_rate Penalty rate as percentage per month (default: 3%)
 * @param float $max_penalty_rate Maximum penalty as percentage of amount (default: 25%)
 * @return float Penalty amount
 */
function calculate_penalty($amount_due, $days_overdue, $penalty_rate = 3.0, $max_penalty_rate = 25.0)
{
    if ($days_overdue <= 0) {
        return 0.00;
    }

    // Calculate monthly penalty rate (pro-rated by days)
    $months_overdue = $days_overdue / 30;
    $penalty_amount = $amount_due * ($penalty_rate / 100) * $months_overdue;

    // Apply maximum penalty cap
    $max_penalty = $amount_due * ($max_penalty_rate / 100);
    $penalty_amount = min($penalty_amount, $max_penalty);

    return round($penalty_amount, 2);
}

/**
 * Calculate remaining balance for a property
 * 
 * @param PDO $pdo Database connection
 * @param int $property_id Property ID
 * @return array ['total_due', 'total_paid', 'remaining_balance', 'overdue_amount']
 */
function calculate_property_balance($pdo, $property_id)
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(ps.amount_due), 0) as total_due,
                COALESCE(SUM(ps.penalty_amount), 0) as total_penalties,
                COALESCE((
                    SELECT SUM(pay.amount_paid) 
                    FROM payments pay 
                    INNER JOIN payment_schedules ps2 ON pay.schedule_id = ps2.schedule_id
                    WHERE ps2.property_id = ?
                ), 0) as total_paid,
                COALESCE(SUM(CASE WHEN ps.status = 'overdue' THEN ps.amount_due ELSE 0 END), 0) as overdue_amount
            FROM payment_schedules ps
            WHERE ps.property_id = ?
        ");
        $stmt->execute([$property_id, $property_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $total_due = $result['total_due'] + $result['total_penalties'];
        $remaining_balance = $total_due - $result['total_paid'];

        return [
            'total_due' => round($total_due, 2),
            'total_penalties' => round($result['total_penalties'], 2),
            'total_paid' => round($result['total_paid'], 2),
            'remaining_balance' => round(max(0, $remaining_balance), 2),
            'overdue_amount' => round($result['overdue_amount'], 2)
        ];

    } catch (PDOException $e) {
        error_log("Calculate property balance error: " . $e->getMessage());
        return [
            'total_due' => 0,
            'total_penalties' => 0,
            'total_paid' => 0,
            'remaining_balance' => 0,
            'overdue_amount' => 0
        ];
    }
}

/**
 * Format currency in Philippine Peso
 * 
 * @param float $amount Amount to format
 * @param bool $show_symbol Whether to show ₱ symbol
 * @return string Formatted amount
 */
function format_peso($amount, $show_symbol = true)
{
    $formatted = number_format($amount, 2);
    return $show_symbol ? '₱' . $formatted : $formatted;
}

/**
 * Calculate total contract value with interest
 * 
 * @param float $total_price Property total price
 * @param float $security_deposit Security deposit (separate from principal, not deducted)
 * @param float $annual_rate Annual interest rate percentage
 * @param int $term_months Payment term in months
 * @return array Contract breakdown
 */
function calculate_contract_summary($total_price, $security_deposit, $annual_rate, $term_months)
{
    $principal = $total_price; // Security deposit does NOT reduce principal
    $amort = calculate_amortization($principal, $annual_rate, $term_months);

    return [
        'total_price'      => round($total_price, 2),
        'security_deposit' => round($security_deposit, 2),
        'principal'        => round($principal, 2),
        'interest_rate'    => $annual_rate,
        'term_months'      => $term_months,
        'monthly_payment'  => $amort['monthly_payment'],
        'total_interest'   => $amort['total_interest'],
        'total_payment'    => round($security_deposit + $amort['total_payment'], 2)
    ];
}
?>