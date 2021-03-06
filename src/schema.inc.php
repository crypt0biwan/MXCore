<?php
// MIT License
//
// Copyright (c) 2018 MXCCoin
//
// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in all
// copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
// SOFTWARE.
//


$dbversion = (isset($_CONFIG['dbversion'])) ? intval($_CONFIG['dbversion']):0;
if ($dbversion == 0) {

    $db->db->query("
    CREATE TABLE IF NOT EXISTS `blocks` (
      `height` int(200) unsigned NOT NULL,
      `block_previous` varchar(64) DEFAULT NULL,
      `block_hash` varchar(64) NOT NULL,
      `root_merkle` varchar(64) NOT NULL,
      `nonce` bigint(200) NOT NULL,
      `timestamp_start_miner` varchar(12) NOT NULL,
      `timestamp_end_miner` varchar(12) NOT NULL,
      `difficulty` varchar(255) NOT NULL,
      `version` varchar(10) NOT NULL,
      `info` text NOT NULL,
      PRIMARY KEY (`height`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $db->db->query("
    CREATE TABLE IF NOT EXISTS `transactions` (
      `txn_hash` varchar(64) NOT NULL,
      `block_hash` varchar(64) NOT NULL,
      `wallet_from_key` longtext,
      `wallet_from` varchar(64) DEFAULT NULL,
      `wallet_to` varchar(64) NOT NULL,
      `amount` varchar(64) NOT NULL,
      `signature` longtext NOT NULL,
      `tx_fee` varchar(10) DEFAULT NULL,
      `timestamp` varchar(12) NOT NULL,
      PRIMARY KEY (`txn_hash`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $db->db->query("
    CREATE TABLE IF NOT EXISTS `transactions_pending` (
      `txn_hash` varchar(64) NOT NULL,
      `block_hash` varchar(64) NOT NULL,
      `wallet_from_key` longtext,
      `wallet_from` varchar(64) DEFAULT NULL,
      `wallet_to` varchar(64) NOT NULL,
      `amount` varchar(64) NOT NULL,
      `signature` longtext NOT NULL,
      `tx_fee` varchar(10) DEFAULT NULL,
      `timestamp` varchar(12) NOT NULL,
      PRIMARY KEY (`txn_hash`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $db->db->query("
    CREATE TABLE IF NOT EXISTS `transactions_pending_to_send` (
      `txn_hash` varchar(64) NOT NULL,
      `block_hash` varchar(64) NOT NULL,
      `wallet_from_key` longtext,
      `wallet_from` varchar(64) DEFAULT NULL,
      `wallet_to` varchar(64) NOT NULL,
      `amount` varchar(64) NOT NULL,
      `signature` longtext NOT NULL,
      `tx_fee` varchar(10) DEFAULT NULL,
      `timestamp` varchar(12) NOT NULL,
      PRIMARY KEY (`txn_hash`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $db->db->query("
    CREATE TABLE IF NOT EXISTS `peers` (
      `id` int(11) NOT NULL,
      `ip` varchar(120) NOT NULL,
      `port` varchar(8) NOT NULL,
      PRIMARY KEY (`ip`,`port`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $db->db->query("
    CREATE TABLE IF NOT EXISTS `mined_blocks_by_peers` (
      `previous_hash` varchar(64) NOT NULL,
      `block` blob NOT NULL,
      PRIMARY KEY (`previous_hash`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");

    $db->db->query("
    CREATE TABLE IF NOT EXISTS `config` (
      `cfg` varchar(200) NOT NULL,
      `val` varchar(200) NOT NULL,
      PRIMARY KEY (`cfg`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");
    $db->db->query("INSERT INTO config SET cfg='dbversion', val='1';");

    Display::_printer("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}

if ($dbversion == 1) {
    $db->db->query("
    ALTER TABLE `peers`
    ADD COLUMN `id`  int(11) UNSIGNED NOT NULL AUTO_INCREMENT FIRST ,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (`id`);
    ");

    $db->db->query("
    ALTER TABLE `transactions`
    ADD INDEX `wallet_from_to` (`wallet_from`, `wallet_to`) USING HASH;
    ");

    $db->db->query("
    ALTER TABLE `transactions_pending`
    ADD INDEX `wallet_from_to` (`wallet_from`, `wallet_to`) USING HASH;
    ");

    $db->db->query("
    ALTER TABLE `transactions_pending_to_send`
    ADD INDEX `wallet_from_to` (`wallet_from`, `wallet_to`) USING HASH;
    ");

    $db->db->query("
    ALTER TABLE `mined_blocks_by_peers`
    MODIFY COLUMN `block`  text NOT NULL AFTER `previous_hash`;
    ");


    Display::_printer("Updating DB Schema #".$dbversion);

    //Increment version to next stage
    $dbversion++;
}

// update dbversion
if ($dbversion != $_CONFIG['dbversion']) {
    $db->db->query("UPDATE config SET val='".$dbversion."' WHERE cfg='dbversion'");
}

Display::_printer("DB Schema updated");

?>