<?php
namespace App\Services;

use drupol\phpermutations\Generators\Combinations;

class AprioriService
{
    private $supports = [];

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

    public function getSupports()
    {
        return $this->supports;
    }
//========================================================== aprori algorithm ===========================================================

    public function findFrequentItemsets($transactions, $minSupport)
    {
        $frequentItemSets = [];

        // Tạo tập ứng viên ban đầu từ các sản phẩm
        $candidateSets = $this->getCandidateSets($transactions);
        //var_dump($candidateSets);
        // Lặp lại quá trình cho đến khi không có tập ứng viên nào mới được tạo ra
        while (!empty($candidateSets)) {
            // Tính toán support cho các tập ứng viên
            $supports = $this->calculateSupports($candidateSets, $transactions);
            //var_dump($supports);
            //push support vào mảng supports
            $this->supports = array_merge($this->supports, $supports);

            // Loại bỏ các tập ứng viên có support thấp
            $prunedCandidateSets = $this->pruneCandidateSets($supports, $minSupport);

            // Thêm các tập ứng viên vào danh sách các mẫu phổ biến
            $frequentItemSets = array_merge($frequentItemSets, $prunedCandidateSets);

            // chuyển chuỗi thành mảng
            $prunedCandidateSets = array_map(function ($key) {
                return explode(',', $key);
            }, array_keys($prunedCandidateSets));
            
            // Tạo các tập kết hợp mới từ các tập ứng viên còn lại 
            //Nếu một itemset là phổ biến thì tất cả các tập con của nó cũng phải là các tập phổ biến
            //do đã loại bỏ các tập ứng viên con có support thấp nên không cần kiểm tra tập con của tập ứng viên
            $candidateSets = $this->generateNextCandidateSets($prunedCandidateSets);
        }
        //var_dump($this->supports);
        //dd($this-> supports['A,B']);
        ksort($frequentItemSets);
        return $frequentItemSets;
    }
    
    /**
     * Tạo tập ứng viên ban đầu từ các sản phẩm
     * @param array $transactions vd: [['A', 'B'], ['A', 'C']]
     * @return array
     */
    private function getCandidateSets($transactions)
    {
        $candidateSets = [];

        foreach ($transactions as $transaction) { // Mỗi giao dịch
            foreach ($transaction as $item) { // Mỗi sản phẩm trong giao dịch
                if (!in_array([$item], $candidateSets)) { // Nếu sản phẩm chưa tồn tại trong tập ứng viên
                    $candidateSets[] = [$item]; // Thêm sản phẩm vào tập ứng viên
                }
            }
        }
        return $candidateSets;
    }
    
    /**
     * Tính support cho các tập ứng viên
     * @param array $candidateSets vd: [['A', 'B'], ['A', 'C']]
     * @param array $transactions vd: [['A', 'B'], ['A', 'C']]
     * @return array vd: ['A,B' => 0.5, 'A,C' => 0.3]
     */
    private function calculateSupports($candidateSets, $transactions)
    {
        $supports = [];
        foreach ($candidateSets as $candidateSet) { // Mỗi tập ứng viên
            $support = 0;
            foreach ($transactions as $transaction) { // Mỗi giao dịch
                if ($this->isSubset($candidateSet, $transaction)) { // Nếu tập ứng viên là tập con của giao dịch
                    $support++;
                }
            }
            // Tính support bằng cách chia số lần xuất hiện của tập ứng viên cho tổng số giao dịch
            $supports[implode(',', $candidateSet)] = $support / count($transactions);
        }
        return $supports;
    }

    /**
     * Kiểm tra xem tập ứng viên có phải là tập con của giao dịch không
     * @param array $candidateSet vd: ['A', 'B']
     * @param array $transaction vd: ['A', 'B', 'C']
     * @return bool
     */
    private function isSubset($candidateSet, $transaction)
    {
        return count(array_diff($candidateSet, $transaction)) == 0;
    }

    /**
     * Loại bỏ các tập ứng viên có support thấp
     * @param array $supports vd: ['A,B' => 0.5, 'A,C' => 0.3]
     * @param float $minSupport
     * @return array
     */
    private function pruneCandidateSets($supports, $minSupport)
    {
        $prunedCandidateSets = [];
        //var_dump($supports);
        foreach ($supports as $candidateSet => $support) { // Mỗi tập ứng viên và support
            if ($support >= $minSupport) { // Nếu support lớn hơn hoặc bằng ngưỡng
                $prunedCandidateSets[$candidateSet] = $support;
            }
        }
        //var_dump($prunedCandidateSets);
        return $prunedCandidateSets;
    }

    /**
     * Tạo các tập ứng viên mới từ các tập ứng viên cũ
     * @param array $candidateSets vd: [['A', 'B'], ['A', 'C']]
     * @return array vd: [['A', 'B', 'C']]
     */ 
    private function generateNextCandidateSets(array $candidateSets)
    {
        $nextCandidateSets = []; // Mảng chứa các tập ứng viên mới

        $numCandidateSets = count($candidateSets); // Số lượng tập ứng viên

        for ($i = 0; $i < $numCandidateSets; $i++) { // Duyệt qua các tập ứng viên
            for ($j = $i + 1; $j < $numCandidateSets; $j++) { // Duyệt qua các tập ứng viên còn lại
                $union = array_unique(array_merge($candidateSets[$i], $candidateSets[$j])); // Hợp của 2 tập ứng viên

                if (count($union) == count($candidateSets[$i]) + 1) { // Nếu số sản phẩm trong hợp bằng số sản phẩm trong tập ứng viên thứ nhất cộng 1
                    $nextCandidateSets[] = $union;
                }

            }
        }

        // Chuẩn hóa chỉ số mảng của các mảng
        $normalizedArrays = array_map(function ($array) {
            sort($array);
            return array_values($array);
        }, $nextCandidateSets);
        //unique các tập ứng viên mới dù thứ tự các sản phẩm khác nhau
        $nextCandidateSets = array_map('unserialize', array_unique(array_map('serialize', $normalizedArrays)));

        return $nextCandidateSets;
    }
//========================================================= Tạo luật kết hợp (Association Rules) =======================================================================

    /**
     * Tạo luật kết hợp từ các mẫu phổ biến
     * @param array $frequentItemsets vd: ['A,B' => 0.5, 'A,C' => 0.3]
     * @return array $associationRules vd: [['A', 'B', 0.5], ['A', 'C', 0.3]]
     */
    public function generateAssociationRules($frequentItemsets)
    {
        $associationRules = [];
        //var_dump($frequentItemsets);
        foreach (array_keys($frequentItemsets) as $frequentItemset) { // Mỗi tập phổ biến
            $frequentItemset = explode(',', $frequentItemset); // Chuyển tập phổ biến thành mảng các sản phẩm
            //var_dump($frequentItemset);
            $numItems = count($frequentItemset); // Đếm số sản phẩm trong tập phổ biến
            //dd ($numItems);
            if ($numItems > 1) { // Nếu tập phổ biến có nhiều hơn 1 sản phẩm
                $this->generateAssociationRulesForItemset($frequentItemset, $associationRules);
            }
        }

        return $associationRules;
    }

    /**
     * Tạo luật kết hợp từ một tập phổ biến
     * @param array $frequentItemset vd: ['A', 'B']
     * @param array $associationRules
     * @return array $associationRules vd: [['A', 'B', 0.5], ['A', 'C', 0.3]]
     */
    private function generateAssociationRulesForItemset($frequentItemset, &$associationRules)
    {
        $numItems = count($frequentItemset);
        for ($i = 1; $i < $numItems; $i++) {
            $combinations = new Combinations($frequentItemset, $i); // Tạo tập hợp các tập con có i phần tử
            foreach ($combinations->generator() as $antecedent) { // Duyệt qua các tập con
                sort($antecedent); // Sắp xếp tập con
                //var_dump($antecedent);
                $consequent = array_diff($frequentItemset, $antecedent); // Tìm tập hợp phần tử không thuộc tập con
                //var_dump($consequent);
                sort($consequent); // Sắp xếp tập hợp phần tử không thuộc tập con

                if (!empty($antecedent) && !empty($consequent)) {
                    $confidence = $this->calculateConfidence($antecedent, $consequent); // Tính confidence
                    //$associationRules[] = [implode(',', $antecedent), implode(',', $consequent), $confidence];
                    if ($confidence >= $this->confidence) { // Nếu confidence lớn hơn ngưỡng
                        $associationRules[] = [implode(',',$antecedent), implode(',',$consequent), $confidence]; // Thêm luật kết hợp vào mảng luật
                    }
                }
            }
        }
        //sort theo confidence giảm dần
        usort($associationRules, function ($a, $b) {
            return $b[2] <=> $a[2];
        });
        return $associationRules;
    }

    /**
     * Tính confidence của một luật kết hợp
     * @param array $leftItem vd: ['A']
     * @param array $rightItem vd: ['B']
     * @return float vd: 0.5
     */
    private function calculateConfidence($leftItem, $rightItem)
    {
        $union = array_unique(array_merge($leftItem, $rightItem)); // Hợp của 2 tập sản phẩm
        sort($union); // Sắp xếp tập hợp
        $supportUnion = $this->calculateSupport(implode(',', $union)); // Support của hợp
        $supportleftItem = $this->calculateSupport(implode(',', $leftItem)); // Support của tập sản phẩm bên trái
        //var_dump($supportUnion);
        //var_dump($supportleftItem);
        //var_dump($leftItem);
        return round($supportUnion / $supportleftItem, 3); // Tính confidence = số lần mua cả 2 sản phẩm / số lần mua sản phẩm bên trái
    }

    /**
     * //tính support của một tập sản phẩm băng cách lấy support từ tập phổ biến
     * @param string $itemset vd: 'A,B'
     * @return float vd: 0.5
     */
    private function calculateSupport($itemset)
    {
        if (!isset($this->supports[$itemset])) {
            return 0;
        }
        return $this->supports[$itemset];
    }
}
