<?php

use App\Helper\Helper;

test('Helper::formattedMoneyToNumber works correctly with various currency formats', function () {
    // 1. Empty/null value should return 0
    expect(Helper::formattedMoneyToNumber(''))->toEqual(0);
    expect(Helper::formattedMoneyToNumber(null))->toEqual(0);

    // 2. Numeric inputs should remain numeric
    expect(Helper::formattedMoneyToNumber(125.50))->toEqual(125.50);

    // 3. Turkish formatted money (dots for thousands, comma for decimals)
    expect(Helper::formattedMoneyToNumber('162.500,00'))->toEqual('162500.00');
    expect(Helper::formattedMoneyToNumber('1.250,50 ₺'))->toEqual('1250.50');

    // 4. Decimal formats with comma
    expect(Helper::formattedMoneyToNumber('25,50'))->toEqual('25.50');
    expect(Helper::formattedMoneyToNumber('25'))->toEqual('25');

    // 5. Numeric string with dot
    expect(Helper::formattedMoneyToNumber('162500'))->toEqual('162500');
});
