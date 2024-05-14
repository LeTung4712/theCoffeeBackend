<?php

namespace App\Services\FpTree;
use drupol\phpermutations\Generators\Combinations; 

class FPTree
{
    private array $frequent;
    private FPNode $root;
    private array $headers;
    
    /**
     * @param array $transactions vd [['A','B'],['A','C']]
     * @param float $minSupport
     * @param $rootValue
     * @param int $rootCount
     * @return void
     */
    public function __construct(array $transactions, float $minSupport, $rootValue, int $rootCount)
    {
        $this->frequent = $this->findFrequentItems($transactions, $minSupport);
        $this->headers = $this->buildHeaderTable();
        $this->root = $this->buildFPTree($transactions, $rootValue, $rootCount, $this->frequent);
    }

    /**
     * hàm tìm các mẫu phổ biến, Quét cơ sở dữ liệu lần đầu tiên để tìm các mẫu phổ biến
     * @param array $transactions
     * @param float $minSupport
     * @return array vd ['A' => 2, 'B' => 2, 'C' => 1]
     */
    protected function findFrequentItems(array $transactions, float $minSupport): array
    {
        $frequentItems = [];
        //var_dump($transactions);
        foreach ($transactions as $transaction) { // Mỗi giao dịch
            foreach ($transaction as $item) { // Mỗi sản phẩm trong giao dịch
                if (array_key_exists($item, $frequentItems)) { // Nếu sản phẩm đã tồn tại trong mảng
                    $frequentItems[$item] += 1; 
                } else { // Nếu sản phẩm chưa tồn tại trong mảng
                    $frequentItems[$item] = 1;
                }
            }
        }
        //var_dump($frequentItems);
        foreach (array_keys($frequentItems) as $key) { // Duyệt qua các sản phẩm
                        
            if ($frequentItems[$key] < $minSupport) { // Nếu số lần xuất hiện của sản phẩm nhỏ hơn ngưỡng
                unset($frequentItems[$key]); // Xóa sản phẩm khỏi mảng
            }
            
        }

        arsort($frequentItems); // Sắp xếp mảng theo giá trị giảm dần
        //var_dump($frequentItems);
        return $frequentItems;
    }

    /**
     * hàm xây dựng bảng header chứa các item phổ biến trên ngưỡng minSupport
     * @return array vd ['A' => null, 'B' => null, 'C' => null]
     */
    protected function buildHeaderTable(): array
    {
        $headers = [];
        foreach (array_keys($this->frequent) as $key) {
            $headers[$key] = null;
        }
        //var_dump($headers);
        return $headers;
    }

    /**
     * hàm xây dựng cây FP-Tree, Quét cơ sở dữ liệu lần thứ hai để xây dựng cây FP-Tree
     * @param array $transactions
     * @param $rootValue
     * @param int $rootCount
     * @param array $frequent
     * @return FPNode
     */
    protected function buildFPTree($transactions, $rootValue, $rootCount, &$frequent): FPNode 
    {
        $root = new FPNode($rootValue, $rootCount, null); // Tạo nút gốc
        arsort($frequent); // Sắp xếp mảng theo giá trị giảm dần
        //var_dump($frequent);
        foreach ($transactions as $transaction) { 
            $sortedItems = []; 
            foreach ($transaction as $item) { 
                if (isset($frequent[$item])) { // Nếu sản phẩm tồn tại trong mảng phổ biến
                    $sortedItems[] = $item; // Thêm sản phẩm vào mảng sortedItems
                }
            }
            // Sắp xếp các sản phẩm trong giao dịch theo thứ tự giảm dần của support
            usort($sortedItems, function ($a, $b) use ($frequent) { 
                return $frequent[$b] <=> $frequent[$a];
            });
            //var_dump($sortedItems);
            if (count($sortedItems) > 0) { // Nếu giao dịch không rỗng
                $this->insertTree($sortedItems, $root);
            }
        }
        return $root;
    }

    //hàm chèn cây
    protected function insertTree(array $items, FPNode $node): void
    {
        $first = $items[0]; // Lấy sản phẩm đầu tiên
        $child = $node->getChild($first); // Lấy con của nút hiện tại

        if ($child !== null) { // Nếu con tồn tại thì tăng số lần xuất hiện của con lên 1
            $child->count += 1;
        } else {
            // Add new child
            $child = $node->addChild($first);
            // Link it to header structure.
            if ($this->headers[$first] === null) {
                $this->headers[$first] = $child;
            } else {
                $current = $this->headers[$first];
                while ($current->link !== null) {
                    $current = $current->link;
                }
                $current->link = $child;
            }
        }

        // Call function recursively.
        $remainingItems = array_slice($items, 1, null);

        if (count($remainingItems) > 0) {
            $this->insertTree($remainingItems, $child);
        }
    }

    //hàm khai thác các mẫu từ cây FP-Tree
    public function minePatterns(float $minSupport): array
    {
        if ($this->treeHasSinglePath($this->root)) { // Nếu cây chỉ có một đường đi
            //echo "Single path tree\n";
            return $this->generatePatternList(); // Tạo danh sách mẫu
        }else {
            //echo "Multi path tree\n";
            return $this->zipPatterns($this->mineSubTrees($minSupport));
        }
    }
    
    //hàm đệ quy kiểm tra xem cây có đường đi duy nhất không
    protected function treeHasSinglePath(FPNode $node): bool
    {
        $childrenCount = count($node->children);// Đếm số lượng con của nút

        if ($childrenCount > 1) {
            return false;
        }

        if ($childrenCount === 0) {
            return true;
        }

        return $this->treeHasSinglePath(current($node->children));
    }

    //hàm nén các mẫu
    protected function zipPatterns(array $patterns): array
    {
        if ($this->root->value === null) { 
            return $patterns;
        }

        // We are in a conditional tree.
        $newPatterns = [];
        foreach (array_keys($patterns) as $strKey) {
            $key = explode(',', $strKey);
            $key[] = $this->root->value;
            sort($key);
            $newPatterns[implode(',', $key)] = $patterns[$strKey];
        }

        return $newPatterns;
    }

    //hàm tạo danh sách mẫu
    protected function generatePatternList(): array
    {
        $patterns = [];
        $items = array_keys($this->frequent); // Lấy danh sách các sản phẩm phổ biến
        //var_dump($items);

        // If we are in a conditional tree, the suffix is a pattern on its own.
        if ($this->root->value !== null) {
            $patterns[$this->root->value] = $this->root->count;
        }

        // Duyệt qua các mẫu từ 1 đến số sản phẩm phổ biến 
        for ($i = 1; $i <= count($items); $i++) { 
            $combinations = new Combinations($items,$i); // Tạo tất cả các tổ hợp có thể
            foreach ($combinations->generator() as $subset) { // Duyệt qua từng tổ hợp
                // Nếu có nút gốc thì thêm nút gốc vào mảng
                $pattern = $this->root->value !== null ? array_merge($subset, [$this->root->value]) : $subset; 
                sort($pattern);
                $min = PHP_INT_MAX; 
                
                foreach ($subset as $x) { // Duyệt qua từng sản phẩm trong tổ hợp
                    if ($this->frequent[$x] < $min) { // Nếu số lần xuất hiện của sản phẩm nhỏ hơn min
                        $min = $this->frequent[$x]; // Gán min bằng số lần xuất hiện của sản phẩm
                    }
                }
                $patterns[implode(',', $pattern)] = $min; // Thêm mẫu vào mảng
            }
        }

        return $patterns;
    }

    //hàm khai thác các mẫu từ các cây con, cây con được tạo từ các giao dịch chứa một sản phẩm cụ thể
    protected function mineSubTrees(float $minSupport): array
    {
        $patterns = [];
        $miningOrder = $this->frequent;
        asort($miningOrder);
        $miningOrder = array_keys($miningOrder);

        // Get items in tree in reverse order of occurrences.
        foreach ($miningOrder as $item) {
            
            $suffixes = [];
            $conditionalTreeInput = [];
            $node = $this->headers[$item];

            // Follow node links to get a list of all occurrences of a certain item.
            while ($node !== null) {
                $suffixes[] = $node;
                $node = $node->link;
            }

            // For each currence of the item, trace the path back to the root node.
            foreach ($suffixes as $suffix) {
                $frequency = $suffix->count;
                $path = [];
                $parent = $suffix->parent;
                while ($parent->parent !== null) {
                    $path[] = $parent->value;
                    $parent = $parent->parent;
                }
                for ($i = 0; $i < $frequency; $i++) {
                    $conditionalTreeInput[] = $path;
                }
            }

            // Now we have the input for a subtree, so construct it and grab the patterns.
            $subtree = new FPTree($conditionalTreeInput, $minSupport, $item, $this->frequent[$item]);
            $subtreePatterns = $subtree->minePatterns($minSupport);

            // Insert subtree patterns into main patterns dictionary.
            foreach (array_keys($subtreePatterns) as $pattern) {
                if (in_array($pattern, $patterns)) {
                    $patterns[$pattern] += $subtreePatterns[$pattern];
                } else {
                    $patterns[$pattern] = $subtreePatterns[$pattern];
                }
            }
        }

        return $patterns;
    }
    public function getRoot()
    {
        return $this->root;
    }

    public function getHeaderTable()
    {
        return $this->headers;
    }

}