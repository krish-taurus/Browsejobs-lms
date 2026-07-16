<?php

declare(strict_types=1);

namespace App\Enums;

enum VoucherIssueStatus: string
{
    case Issued = 'issued';   // minted, not yet applied to a fee plan
    case Applied = 'applied'; // redeemed against a fee plan
    case Expired = 'expired'; // past its expiry window
    case Void = 'void';       // revoked
}
