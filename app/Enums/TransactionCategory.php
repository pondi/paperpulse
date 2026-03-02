<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionCategory: string
{
    case Income = 'income';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case LoanPayments = 'loan_payments';
    case BankFees = 'bank_fees';
    case Entertainment = 'entertainment';
    case FoodAndDrink = 'food_and_drink';
    case GeneralMerchandise = 'general_merchandise';
    case HomeImprovement = 'home_improvement';
    case Medical = 'medical';
    case PersonalCare = 'personal_care';
    case GeneralServices = 'general_services';
    case GovernmentAndNonProfit = 'government_and_non_profit';
    case Transportation = 'transportation';
    case Travel = 'travel';
    case RentAndUtilities = 'rent_and_utilities';

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Income',
            self::TransferIn => 'Transfer In',
            self::TransferOut => 'Transfer Out',
            self::LoanPayments => 'Loan Payments',
            self::BankFees => 'Bank Fees',
            self::Entertainment => 'Entertainment',
            self::FoodAndDrink => 'Food & Drink',
            self::GeneralMerchandise => 'General Merchandise',
            self::HomeImprovement => 'Home Improvement',
            self::Medical => 'Medical',
            self::PersonalCare => 'Personal Care',
            self::GeneralServices => 'General Services',
            self::GovernmentAndNonProfit => 'Government & Non-Profit',
            self::Transportation => 'Transportation',
            self::Travel => 'Travel',
            self::RentAndUtilities => 'Rent & Utilities',
        };
    }

    /**
     * @return array<string, list<string>>
     */
    public static function subcategories(): array
    {
        return [
            self::Income->value => [
                'Wages', 'Dividends', 'Interest', 'Tax Refund', 'Retirement/Pension',
                'Unemployment', 'Freelance', 'Rental Income', 'Other Income',
            ],
            self::TransferIn->value => [
                'Deposit', 'Investment Transfer', 'Cash Advance', 'Loan Disbursement',
                'Internal Transfer', 'Other Transfer In',
            ],
            self::TransferOut->value => [
                'Investment Transfer', 'Savings Transfer', 'Withdrawal',
                'Internal Transfer', 'Other Transfer Out',
            ],
            self::LoanPayments->value => [
                'Mortgage', 'Student Loan', 'Car Payment', 'Credit Card Payment',
                'Personal Loan', 'Other Loan',
            ],
            self::BankFees->value => [
                'ATM Fee', 'Foreign Transaction Fee', 'Insufficient Funds',
                'Interest Charge', 'Overdraft Fee', 'Service Charge', 'Other Fee',
            ],
            self::Entertainment->value => [
                'Casino/Gambling', 'Music/Audio', 'Sporting Events', 'TV/Movies',
                'Video Games', 'Streaming Services', 'Other Entertainment',
            ],
            self::FoodAndDrink->value => [
                'Groceries', 'Restaurant', 'Fast Food', 'Coffee Shop',
                'Beer/Wine/Liquor', 'Food Delivery', 'Other Food',
            ],
            self::GeneralMerchandise->value => [
                'Clothing', 'Electronics', 'Discount Store', 'Bookstore',
                'Office Supplies', 'Gifts', 'Pets', 'Sporting Goods', 'Online Shopping',
            ],
            self::HomeImprovement->value => [
                'Furniture', 'Hardware', 'Repair/Maintenance', 'Appliances',
                'Garden/Outdoor', 'Other Home',
            ],
            self::Medical->value => [
                'Dental', 'Eye Care', 'Pharmacy', 'Primary Care', 'Specialist',
                'Hospital', 'Veterinary', 'Other Medical',
            ],
            self::PersonalCare->value => [
                'Gym/Fitness', 'Hair/Beauty', 'Laundry', 'Spa/Massage', 'Other Personal Care',
            ],
            self::GeneralServices->value => [
                'Accounting', 'Automotive', 'Insurance', 'Legal', 'Postage/Shipping',
                'Storage', 'Childcare', 'Education', 'Other Services',
            ],
            self::GovernmentAndNonProfit->value => [
                'Donations', 'Government Services', 'Tax Payment', 'Fines',
            ],
            self::Transportation->value => [
                'Gas/Fuel', 'Parking', 'Public Transit', 'Ride Share', 'Tolls',
                'Vehicle Maintenance', 'Other Transportation',
            ],
            self::Travel->value => [
                'Flights', 'Lodging', 'Rental Car', 'Vacation', 'Other Travel',
            ],
            self::RentAndUtilities->value => [
                'Rent', 'Mortgage Payment', 'Gas/Electric', 'Internet/Cable',
                'Phone', 'Water', 'Sewage/Waste', 'Other Utilities',
            ],
        ];
    }
}
