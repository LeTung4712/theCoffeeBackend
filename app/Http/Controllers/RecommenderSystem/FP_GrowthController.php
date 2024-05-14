<?php

namespace App\Http\Controllers\RecommenderSystem;

use App\Http\Controllers\Controller;
use App\Services\FP_GrowthService;
use Illuminate\Http\Request;

class FP_GrowthController extends Controller
{
    public function analyzeFpGrowth(Request $request)
    {
        $minSupport = 0.2;
        $confidence = 0.7;
        $fpGrowthService = new FP_GrowthService($minSupport*count($this->transactions), $confidence);
        $frequentItemsets = $fpGrowthService->findFrequentItemsets($this->transactions);
        $associationRules = $fpGrowthService->generateAssociationRules($frequentItemsets); 
        return response()->json([
            'count transactions' => count($this->transactions),
            'frequentItemsets' => $frequentItemsets,
            'associationRules' => $associationRules,
        ]);

    }

    private $transactions = [
        ['I1', 'I2', 'I5'],
        ['I2', 'I4'],
        ['I2', 'I3'],
        ['I1', 'I3', 'I2', 'I4'],
        ['I1', 'I3'],
        ['I2', 'I3'],
        ['I1', 'I3'],
        ['I1', 'I2', 'I3', 'I5'],
        ['I1', 'I2', 'I3']
    ];
}
