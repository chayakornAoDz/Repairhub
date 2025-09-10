<?php
// ตรึง mapping ให้ตรงกับฐานข้อมูลจริงของโปรเจกต์
// เรียกใช้จาก issue.php / loan.php
function rh_get_inventory_map(PDO $pdo){
  return [
    // ตารางสินค้า
    'TB_INV'  => 'inventory_items',
    'C_INV'   => [
      'id'   => 'id',
      'name' => 'name',
      'sku'  => 'sku',
      'unit' => 'unit',
      'qty'  => 'stock_qty',
    ],

    // ตารางความเคลื่อนไหวสต็อค
    'TB_MOVE' => 'stock_movements',
    'C_MOVE'  => [
      'id'        => 'id',
      'item_id'   => 'item_id',
      'change_qty'=> 'qty',
      'type'      => 'type',
      'ref'       => 'reference',
      'by'        => 'created_by',
      'at'        => 'created_at',
      'balance'   => 'balance_after', // คงเหลือหลังทำ (มีในฝั่งแอดมิน)
    ],

    // ตารางบันทึกการยืม
    'TB_LOAN' => 'loans',
    'C_LOAN'  => [
      'id'       => 'id',
      'item_id'  => 'item_id',
      'qty'      => 'qty',
      'borrower' => 'requester_name',
      'contact'  => 'contact',
      'dept'     => null,           // ฝั่งคุณไม่มีคอลัมน์แผนก → ไม่ใช้
      'due'      => 'due_date',
      'status'   => 'status',
      'at'       => 'created_at',
    ],
  ];
}
