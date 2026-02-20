<?php

return [
    'chain' => env('BLOCKCHAIN_CHAIN', 'polygon'),
    'contract_address' => env('BLOCKCHAIN_CONTRACT_ADDRESS', '0x0000000000000000000000000000000000000000'),
    'wallet_master_key' => env('WALLET_MASTER_KEY', ''),
    'encryption_version' => (int) env('WALLET_ENCRYPTION_VERSION', 1),
];
