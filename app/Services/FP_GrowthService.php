<?php
namespace App\Services;

use App\Services\FpTree\FPTree;
use drupol\phpermutations\Generators\Combinations;

class FP_GrowthService
{
    protected float $minSupport;
    protected float $confidence;

    public function __construct(float $minSupport, float $confidence)
    {
        $this->setSupport($minSupport);
        $this->setConfidence($confidence);
    }

    public function setSupport(float $minSupport)
    {
        $this->minSupport = $minSupport;
    }

    public function setConfidence(float $confidence)
    {
        $this->confidence = $confidence;
    }

//========================================================== FP-Growth algorithm ===========================================================

    /**
     * Tìm các mẫu kết hợp thường xuyên
     * @param array $transactions
     * @return array
     */
    public function findFrequentItemsets($transactions)
    {
        // Bước 1: Xây dựng cây FP-Tree từ danh sách giao dịch
        $fpTree = new FPTree($transactions, $this->minSupport, null, 0);
        // Bước 2: Tạo các mẫu kết hợp thường xuyên từ FP-Tree
        $frequentItemsets   = $fpTree->minePatterns($this->minSupport);
        $singleItemSupports = $fpTree->getSingleItemSupports();

        // Gộp kết quả và ưu tiên giá trị từ singleItemSupports cho các item đơn lẻ
        foreach ($singleItemSupports as $item => $support) {
            $frequentItemsets[$item] = $support;
        }
        // Chuyển đổi support từ số lần xuất hiện sang tỉ lệ
        $totalTransactions = count($transactions);
        foreach ($frequentItemsets as $pattern => $count) {
            $frequentItemsets[$pattern] = round($count / $totalTransactions, 3);
        }

        // sort theo key
        ksort($frequentItemsets);
        return $frequentItemsets;
    }

//========================================================= Tạo luật kết hợp (Association Rules) =======================================================================

    /**
     * Tạo luật kết hợp từ các mẫu phổ biến
     * @param array $patterns
     * @return array
     */
    public function generateAssociationRules(array $patterns): array
    {
        $rules = [];
        foreach (array_keys($patterns) as $pattern) {            // Duyệt qua các mẫu phổ biến
            $itemSet      = explode(',', $pattern);                  // Chuyển mẫu phổ biến thành mảng các sản phẩm
            $upperSupport = $patterns[$pattern];                     // Support của mẫu phổ biến (đã là tỉ lệ)
            for ($i = 1; $i < count($itemSet); $i++) {               // Duyệt qua các tập con của mẫu phổ biến
                $combinations = new Combinations($itemSet, $i);          // Tạo tập hợp các tập con có i phần tử
                foreach ($combinations->generator() as $antecedent) {    // Duyệt qua các tập con
                    sort($antecedent);                                       // Sắp xếp tập con
                    $antecedentStr = implode(',', $antecedent);              // Chuyển tập con thành chuỗi
                    $consequent    = array_diff($itemSet, $antecedent);      // Tìm tập hợp phần tử không thuộc tập con
                    sort($consequent);                                       // Sắp xếp tập hợp phần tử không thuộc tập con
                    $consequentStr = implode(',', $consequent);              // Chuyển tập hợp phần tử không thuộc tập con thành chuỗi
                    if (isset($patterns[$antecedentStr])) {                  // Nếu tập con tồn tại trong mảng mẫu phổ biến
                        $lowerSupport = $patterns[$antecedentStr];               // Support của tập con (đã là tỉ lệ)
                        $confidence   = round($upperSupport / $lowerSupport, 3); // Tính độ tin cậy

                        if ($confidence >= $this->confidence) { // Nếu độ tin cậy lớn hơn ngưỡng
                                                                    // Tính lift
                            $consequentSupport = isset($patterns[$consequentStr]) ? $patterns[$consequentStr] : 0;
                            $lift              = $consequentSupport > 0 ? round($confidence / $consequentSupport, 3) : 0;

                            // Thêm luật kết hợp vào mảng luật với support và lift
                            $rules[] = [
                                $antecedentStr,
                                $consequentStr,
                                $confidence,
                                $upperSupport, // support của luật (đã là tỉ lệ)
                                $lift,
                            ];
                        }
                    }
                }
            }
        }
        //sort theo confidence
        usort($rules, function ($a, $b) {
            return $b[2] <=> $a[2];
        });
        return $rules;
    }
}
