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

class DB {

    public $db;

    /**
     * DB constructor.
     */
    public function __construct() {

        //We create or load the database
        $this->db = new mysqli(DB_HOST,DB_USER,DB_PASS,DB_NAME, DB_PORT);

        //We check if the tables needed for the blockchain are created
        $this->CheckIfExistTables();
    }

    /**
     * @param $table
     * @return bool
     */
    public function truncate($table) {
        if ($this->db->query("TRUNCATE TABLE " . $table.";"))
            return true;
        return false;
    }

    public function GetConfig() {
        $_CONFIG = array();
        $query = $this->db->query("SELECT cfg, val FROM config");
        if (!empty($query)) {
            while ($cfg = $query->fetch_array(MYSQLI_ASSOC))
                $_CONFIG[$cfg['cfg']] = trim($cfg['val']);
        }
        return $_CONFIG;
    }

    /**
     * @return bool|mixed
     */
    public function GetBootstrapNode() {
        //Seleccionamos el primer peer (Que sera el bootstrap node)
        $info_mined_blocks_by_peer = $this->db->query("SELECT * FROM peers LIMIT 1;")->fetch_assoc();
        if (!empty($info_mined_blocks_by_peer)) {
            return $info_mined_blocks_by_peer;
        }
        return false;
    }

    /**
     * Add a peer to the chaindata
     *
     * @param $ip
     * @param $port
     * @return bool
     */
    public function addPeer($ip,$port) {
        $info_mined_blocks_by_peer = $this->db->query("SELECT ip FROM peers WHERE ip = '".$ip."' AND port = '".$port."';")->fetch_assoc();
        if (empty($info_mined_blocks_by_peer)) {
            $this->db->query("INSERT INTO peers (ip,port) VALUES ('".$ip."', '".$port."');");
            return true;
        }
        return false;
    }

    /**
     * Returns whether or not we have this peer saved in the chaindata
     *
     * @param $ip
     * @param $port
     * @return bool
     */
    public function haveThisPeer($ip,$port) {
        $info_mined_blocks_by_peer = $this->db->query("SELECT ip FROM peers WHERE ip = '".$ip."' AND port = '".$port."';")->fetch_assoc();
        if (!empty($info_mined_blocks_by_peer)) {
            return true;
        }
        return false;
    }

    /**
     * Returns a block given a hash
     *
     * @param $hash
     * @return array
     */
    public function GetBlockByHash($hash) {
        $sql = "SELECT * FROM blocks WHERE block_hash = '".$hash."'";
        $info_block = $this->db->query($sql)->fetch_assoc();
        if (!empty($info_block)) {

            $transactions_chaindata = $this->db->query("SELECT * FROM transactions WHERE block_hash = '".$info_block['block_hash']."';");
            $transactions = array();
            if (!empty($transactions_chaindata)) {
                while ($transactionInfo = $transactions_chaindata->fetch_array(SQLITE3_ASSOC)) {
                    $transactions[] = $transactionInfo;
                }
            }

            $info_block["transactions"] = $transactions;

            return $info_block;
        }
        return null;
    }

    /**
     * Returns a block given a hash
     *
     * @param $hash
     * @return array
     */
    public function GetBlockHeightByHash($hash) {
        $sql = "SELECT height FROM blocks WHERE block_hash = '".$hash."' LIMIT 1;";
        $info_block = $this->db->query($sql)->fetch_assoc();
        if (!empty($info_block))
            return $info_block;
        return null;
    }

    /**
     * Returns a transaction given a hash
     *
     * @param $hash
     * @return mixed
     */
    public function GetTransactionByHash($hash) {
        $sql = "SELECT * FROM transactions WHERE txn_hash = '".$hash."';";
        $info_txn = $this->db->query($sql)->fetch_assoc();
        if (!empty($info_txn)) {
            return $info_txn;
        }
        return null;
    }

    /**
     * Returns a pending transaction given a hash
     *
     * @param $hash
     * @return mixed
     */
    public function GetPendingTransactionByHash($hash) {
        $sql = "SELECT * FROM transactions_pending WHERE txn_hash = '".$hash."';";
        $info_txn = $this->db->query($sql)->fetch_assoc();
        if (!empty($info_txn)) {
            return $info_txn;
        }
        return null;
    }

    /**
     * Returns the information of a wallet
     *
     * @param $hash
     * @return array
     */
    public function GetWalletInfo($wallet) {

        $totalReceived = "0";
        $totalSpend = "0";

        $totalReceived_tmp = $this->db->query("SELECT amount FROM transactions WHERE wallet_to = '".$wallet."';");
        if (!empty($totalReceived_tmp)) {
            while ($txnInfo = $totalReceived_tmp->fetch_array(MYSQLI_ASSOC)) {
                $totalReceived = bcadd($totalReceived, $txnInfo['amount'], 8);
            }
        }

        //Obtenemos lo que ha gastado el usuario (pendiente o no de tramitar)
        $totalSpended_tmp = $this->db->query("SELECT amount FROM transactions WHERE wallet_from = '".$wallet."';");
        if (!empty($totalSpended_tmp)) {
            while ($txnInfo = $totalSpended_tmp->fetch_array(MYSQLI_ASSOC)) {
                $totalSpend = bcadd($totalSpend, $txnInfo['amount'], 8);
            }
        }

        $totalSpendedPending_tmp = $this->db->query("SELECT amount FROM transactions_pending WHERE wallet_from = '".$wallet."';");
        if (!empty($totalSpendedPending_tmp)) {
            while ($txnInfo = $totalSpendedPending_tmp->fetch_array(MYSQLI_ASSOC)) {
                $totalSpend = bcadd($totalSpend, $txnInfo['amount'], 8);
            }
        }

        $totalSpendedPendingToSend_tmp = $this->db->query("SELECT amount FROM transactions_pending_to_send WHERE wallet_from = '".$wallet."';");
        if (!empty($totalSpendedPendingToSend_tmp)) {
            while ($txnInfo = $totalSpendedPendingToSend_tmp->fetch_array(MYSQLI_ASSOC)) {
                $totalSpend = bcadd($totalSpend, $txnInfo['amount'], 8);
            }
        }

        $current = bcsub($totalReceived,$totalSpend,8);

        return array(
            'sended' => $totalSpend,
            'received' => $totalReceived,
            'current' => $current
        );
    }

    /**
     * Returns all the transactions of a wallet
     *
     * @param $hash
     * @return array
     */
    public function GetTransactionsByWallet($wallet,$limit=50) {
        $transactions_chaindata = $this->db->query("SELECT * FROM transactions WHERE wallet_to = '".$wallet."' OR wallet_from = '".$wallet."' ORDER BY timestamp DESC LIMIT ".$limit.";");
        $transactions = array();
        if (!empty($transactions_chaindata)) {
            while ($transactionInfo = $transactions_chaindata->fetch_array(MYSQLI_ASSOC)) {
                $transactions[] = $transactionInfo;
            }
        }

        return $transactions;
    }

    /**
     * Returns a block given a height
     *
     * @param $hash
     * @return mixed
     */
    public function GetBlockByHeight($height) {
        $sql = "SELECT * FROM blocks WHERE height = ".$height.";";
        $info_block = $this->db->query($sql)->fetch_assoc();
        if (!empty($info_block)) {

            $transactions_chaindata = $this->db->query("SELECT * FROM transactions WHERE block_hash = '".$info_block['block_hash']."';");
            $transactions = array();
            if (!empty($transactions_chaindata)) {
                while ($transactionInfo = $transactions_chaindata->fetch_array(MYSQLI_ASSOC)) {
                    $transactions[] = $transactionInfo;
                }
            }

            $info_block["transactions"] = $transactions;

            return $info_block;
        }
        return null;
    }

    /**
     * Add a pending transaction to the chaindata
     *
     * @param $ip
     * @param $port
     * @return bool
     */
    public function addPendingTransaction($transaction) {
        $into_tx_pending = $this->db->query("SELECT txn_hash FROM transactions_pending WHERE txn_hash = '".$transaction['txn_hash']."';")->fetch_assoc();
        if (empty($into_tx_pending)) {

            $sql_update_transactions = "INSERT INTO transactions_pending (block_hash, txn_hash, wallet_from_key, wallet_from, wallet_to, amount, signature, tx_fee, timestamp) 
                    VALUES ('','".$transaction['txn_hash']."','".$transaction['wallet_from_key']."','".$transaction['wallet_from']."','".$transaction['wallet_to']."','".$transaction['amount']."','".$transaction['signature']."','".$transaction['tx_fee']."','".$transaction['timestamp']."');";
            $this->db->query($sql_update_transactions);
            return true;
        }
        return false;
    }

    /**
     * Add a pending transaction to the chaindata
     *
     * @param $ip
     * @param $port
     * @return bool
     */
    public function addPendingTransactionByBootstrap($transaction) {
        if (isset($transaction->txn_hash) && strlen($transaction->txn_hash) > 0) {
            $into_tx_pending = $this->db->query("SELECT txn_hash FROM transactions_pending WHERE txn_hash = '".$transaction->txn_hash."';")->fetch_assoc();
            if (empty($into_tx_pending)) {
                $sql_update_transactions = "INSERT INTO transactions_pending (block_hash, txn_hash, wallet_from_key, wallet_from, wallet_to, amount, signature, tx_fee, timestamp) 
                    VALUES ('','".$transaction->txn_hash."','".$transaction->wallet_from_key."','".$transaction->wallet_from."','".$transaction->wallet_to."','".$transaction->amount."','".$transaction->signature."','".$transaction->tx_fee."','".$transaction->timestamp."');";
                $this->db->query($sql_update_transactions);
                return true;
            }
        }
    }

    /**
     * Return array with all pending transactions
     *
     * @return array
     */
    public function GetAllPendingTransactions() {
        $txs = array();
        $txs_chaindata = $this->db->query("SELECT * FROM transactions_pending ORDER BY tx_fee ASC LIMIT 512");
        if (!empty($txs_chaindata)) {
            while ($tx_chaindata = $txs_chaindata->fetch_array(MYSQLI_ASSOC)) {
                if ($tx_chaindata['txn_hash'] != null && strlen($tx_chaindata['txn_hash']) > 0)
                    $txs[] = $tx_chaindata;
            }
        }
        return $txs;
    }

    /**
     * Add pending transactions received by a peer
     *
     * @param $transactionsByPeer
     * @return bool
     */
    public function addPendingTransactionsByPeer($transactionsByPeer) {
        foreach ($transactionsByPeer as $tx)
            $this->addPendingTransaction($tx);

        return true;
    }

    /**
     * Add a pending transaction to send to the chaindata
     *
     * @param $txHash
     * @param $transaction
     * @return bool
     */
    public function addPendingTransactionToSend($txHash,$transaction) {
        $into_tx_pending = $this->db->query("SELECT txn_hash FROM transactions_pending_to_send WHERE txn_hash = '".$txHash."';")->fetch_assoc();
        if (empty($into_tx_pending)) {

            $wallet_from_pubkey = "";
            $wallet_from = "";
            if ($transaction->from != null) {
                $wallet_from_pubkey = $transaction->from;
                $wallet_from = Wallet::GetWalletAddressFromPubKey($transaction->from);
            }

            $sql_update_transactions = "INSERT INTO transactions_pending_to_send (block_hash, txn_hash, wallet_from_key, wallet_from, wallet_to, amount, signature, tx_fee, timestamp) 
                    VALUES ('','".$transaction->message()."','".$wallet_from_pubkey."','".$wallet_from."','".$transaction->to."','".$transaction->amount."','".$transaction->signature."','".$transaction->tx_fee."','".$transaction->timestamp."');";
            $this->db->query($sql_update_transactions);
            return true;
        }
        return false;
    }

    /**
     * Delete a pending transaction
     *
     * @param $ip
     * @param $port
     * @return bool
     */
    public function removePendingTransaction($txHash) {
        $this->db->query("DELETE FROM transactions_pending WHERE txn_hash='".$txHash."';");
    }

    /**
     * Delete a pending transaction to send
     *
     * @param $ip
     * @param $port
     * @return bool
     */
    public function removePendingTransactionToSend($txHash) {
        $this->db->query("DELETE FROM transactions_pending_to_send WHERE txn_hash='".$txHash."';");
    }

    /**
     * Return array with all pending transactions to send
     *
     * @return array
     */
    public function GetAllPendingTransactionsToSend() {
        $txs = array();
        $txs_chaindata = $this->db->query("SELECT * FROM transactions_pending_to_send");
        if (!empty($txs_chaindata)) {
            while ($tx_chaindata = $txs_chaindata->fetch_array(MYSQLI_ASSOC)) {
                $txs[] = $tx_chaindata;
            }
        }
        return $txs;
    }

    /**
     * Remove a peer from the chaindata
     *
     * @param $ip
     * @param $port
     * @return bool
     */
    public function removePeer($ip,$port) {
        //Comprobamos que no hayamos registrado ya este bloque minado
        $info_mined_blocks_by_peer = $this->db->query("SELECT ip FROM peers WHERE ip = '".$ip."' AND port = '".$port."';")->fetch_assoc();
        if (!empty($info_mined_blocks_by_peer)) {
            if ($this->db->query("DELETE FROM peers WHERE ip = '".$ip."' AND port= '".$port."';"))
                return true;
        }
        return false;
    }

    /**
     * Returns an array with all the peers
     *
     * @return array
     */
    public function GetAllPeers() {
        $peers = array();
        $peers_chaindata = $this->db->query("SELECT * FROM peers ORDER BY id");
        if (!empty($peers_chaindata)) {
            while ($peer = $peers_chaindata->fetch_array(MYSQLI_ASSOC)) {
                $ip = str_replace("\r","",$peer['ip']);
                $ip = str_replace("\n","",$ip);

                $port = str_replace("\r","",$peer['port']);
                $port = str_replace("\n","",$port);

                $infoPeer = array(
                    'ip' => $ip,
                    'port' => $port
                );
                $peers[] = $infoPeer;
            }
        }
        return $peers;
    }

    /**
     * Returns an array with 25 random peers
     *
     * @return array
     */
    public function GetPeers() {
        $peers = array();
        $peers_chaindata = $this->db->query("SELECT * FROM peers LIMIT 25");
        if (!empty($peers_chaindata)) {
            while ($peer = $peers_chaindata->fetch_array(MYSQLI_ASSOC)) {
                $infoPeer = array(
                    'ip' => $peer['ip'],
                    'port' => $peer['port']
                );
                $peers[] = $infoPeer;
            }
        }
        return $peers;
    }

    /**
     * Add a block in the chaindata
     *
     * @param $blockNum
     * @param $blockInfo
     * @return bool
     */
    public function addBlock($blockNum,$blockInfo) {
        $info_block_chaindata = $this->db->query("SELECT block_hash FROM blocks WHERE block_hash = '".$blockInfo->hash."';")->fetch_assoc();
        if (empty($info_block_chaindata)) {

            //Check if exist previous
            $block_previous = "";
            if ($blockInfo->previous != null)
                $block_previous = $blockInfo->previous;

            //SQL Insert Block
            $sql_insert_block = "INSERT INTO blocks (height,block_previous,block_hash,root_merkle,nonce,timestamp_start_miner,timestamp_end_miner,difficulty,version,info)
            VALUES (".$blockNum.",'".$block_previous."','".$blockInfo->hash."','".$blockInfo->merkle."','".$blockInfo->nonce."','".$blockInfo->timestamp."','".$blockInfo->timestamp_end."','".$blockInfo->difficulty."','1','".$this->db->real_escape_string(@serialize($blockInfo->info))."');";

            if ($this->db->query($sql_insert_block)) {

                foreach ($blockInfo->transactions as $transaction) {

                    $wallet_from_pubkey = "";
                    $wallet_from = "";
                    if ($transaction->from != null) {
                        $wallet_from_pubkey = $transaction->from;
                        $wallet_from = Wallet::GetWalletAddressFromPubKey($transaction->from);
                    }

                    $sql_update_transactions = "INSERT INTO transactions (block_hash, txn_hash, wallet_from_key, wallet_from, wallet_to, amount, signature, tx_fee, timestamp) 
                    VALUES ('".$blockInfo->hash."','".$transaction->message()."','".$wallet_from_pubkey."','".$wallet_from."','".$transaction->to."','".$transaction->amount."','".$transaction->signature."','".$transaction->tx_fee."','".$transaction->timestamp."');";
                    $this->db->query($sql_update_transactions);


                    //We eliminated the pending transaction
                    $this->removePendingTransaction($transaction->message());
                    $this->removePendingTransactionToSend($transaction->message());

                }
                return true;
            }
        }
        return false;
    }

    /**
     * Add a block mined by a peer by saving the previous_hash and the mined block
     *
     * @param string $previous_hash
     * @param Block $SerializedBlockMined
     * @return bool
     */
    public function AddMinedBlockByPeer($previous_hash, $blockMined) {
        //Check if block is in table
        $info_mined_blocks_by_peer = $this->db->query("SELECT previous_hash FROM mined_blocks_by_peers WHERE previous_hash = '".$previous_hash."';")->fetch_assoc();
        if (empty($info_mined_blocks_by_peer)) {
            if ($this->db->query("INSERT INTO mined_blocks_by_peers (previous_hash,block) VALUES ('" . $previous_hash . "', '" . $blockMined . "');"))
                return true;
        }
        return false;
    }

    /**
     * Return array of mined blocks by peers
     *
     * @return bool|mixed
     */
    public function GetPeersMinedBlocks() {
        $info_mined_blocks_by_peer = $this->db->query("SELECT previous_hash, block FROM mined_blocks_by_peers WHERE previous_hash = '';")->fetch_assoc();
        if (!empty($info_mined_blocks_by_peer)) {
            return $info_mined_blocks_by_peer;
        }
        return false;
    }

    /**
     * Return array of mined blocks by peers
     *
     * @param $previous_hash
     * @return bool|mixed
     */
    public function GetPeersMinedBlockByPrevious($previous_hash) {
        $info_mined_blocks_by_peer = $this->db->query("SELECT previous_hash, block FROM mined_blocks_by_peers WHERE previous_hash = '".$previous_hash."';")->fetch_assoc();
        if (!empty($info_mined_blocks_by_peer)) {
            return $info_mined_blocks_by_peer;
        }
        return false;
    }

    /**
     * Remove a block mined by a peer given a previous_hash
     *
     * @param $previous_hash
     * @return bool
     */
    public function RemovePeerMinedBlockByPrevious($previous_hash) {
        if ($this->db->query("DELETE FROM mined_blocks_by_peers WHERE previous_hash = '".$previous_hash."';"))
            return true;
        return false;
    }

    /**
     * Remove a block mined by a peer given a previous_hash
     *
     * @param $previous_hash
     * @return bool
     */
    public function RemovePeerMinedBlocks() {
        if ($this->db->query("DELETE FROM mined_blocks_by_peers;"))
            return true;
        return false;
    }

    /**
     * Returns the next block number in the block chain
     * Must be the number entered in the next block
     *
     * @return mixed
     */
    public function GetNextBlockNum() {
        return $this->db->query("SELECT COUNT(height) as NextBlockNum FROM blocks")->fetch_assoc()['NextBlockNum'];
    }

    /**
     * Returns the GENESIS block
     *
     * @return mixed
     */
    public function GetGenesisBlock() {
        $genesis_block = null;
        $blocks_chaindata = $this->db->query("SELECT * FROM blocks WHERE height = 0");
        //If we have block information, we will import them into a new BlockChain
        if (!empty($blocks_chaindata)) {
            while ($blockInfo = $blocks_chaindata->fetch_array(MYSQLI_ASSOC)) {

                $transactions_chaindata = $this->db->query("SELECT * FROM transactions WHERE block_hash = '".$blockInfo['block_hash']."';");
                $transactions = array();
                if (!empty($transactions_chaindata)) {
                    while ($transactionInfo = $transactions_chaindata->fetch_array(MYSQLI_ASSOC)) {
                        $transactions[] = $transactionInfo;
                    }
                }

                $blockInfo["transactions"] = $transactions;

                $genesis_block = $blockInfo;
            }
        }
        return $genesis_block;

    }

    /**
     * Returns last block
     *
     * @return mixed
     */
    public function GetLastBlock() {
        $genesis_block = null;
        $blocks_chaindata = $this->db->query("SELECT * FROM blocks ORDER BY height DESC LIMIT 1");
        //If we have block information, we will import them into a new BlockChain
        if (!empty($blocks_chaindata)) {
            while ($blockInfo = $blocks_chaindata->fetch_array(MYSQLI_ASSOC)) {

                $transactions_chaindata = $this->db->query("SELECT * FROM transactions WHERE block_hash = '".$blockInfo['block_hash']."';");
                $transactions = array();
                if (!empty($transactions_chaindata)) {
                    while ($transactionInfo = $transactions_chaindata->fetch_array(MYSQLI_ASSOC)) {
                        $transactions[] = $transactionInfo;
                    }
                }

                $blockInfo["transactions"] = $transactions;

                $genesis_block = $blockInfo;
            }
        }
        return $genesis_block;

    }

    /**
     * Returns the blocks to be synchronized from the block passed by parameter
     *
     * @param $fromBlock
     * @return array
     */
    public function SyncBlocks($fromBlock) {
        $blocksToSync = array();
        $blocks_chaindata = $this->db->query("SELECT * FROM blocks ORDER BY height ASC LIMIT ".$fromBlock.",100");

        //If we have block information, we will import them into a new BlockChain
        if (!empty($blocks_chaindata)) {
            $height = 0;
            while ($blockInfo = $blocks_chaindata->fetch_array(MYSQLI_ASSOC)) {

                $transactions_chaindata = $this->db->query("SELECT * FROM transactions WHERE block_hash = '".$blockInfo['block_hash']."';");
                $transactions = array();
                if (!empty($transactions_chaindata)) {
                    while ($transactionInfo = $transactions_chaindata->fetch_array(MYSQLI_ASSOC)) {
                        $transactions[] = $transactionInfo;
                    }
                }

                $blockInfo["transactions"] = $transactions;

                $blocksToSync[] = $blockInfo;
            }
        }
        return $blocksToSync;
    }

    /**
     * Check that the basic tables exist for the blockchain to work
     */
    private function CheckIfExistTables() {
        //We create the tables by default
        $this->db->query("
        CREATE TABLE `blocks` (
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
        $this->db->query("
        CREATE TABLE `transactions` (
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
        $this->db->query("
        CREATE TABLE `transactions_pending` (
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
        $this->db->query("
        CREATE TABLE `transactions_pending_to_send` (
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
        $this->db->query("
        CREATE TABLE IF NOT EXISTS `peers` (
          `ip` varchar(120) NOT NULL,
          `port` varchar(8) NOT NULL,
          PRIMARY KEY (`ip`,`port`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $this->db->query("
        CREATE TABLE IF NOT EXISTS `mined_blocks_by_peers` (
          `previous_hash` varchar(64) NOT NULL,
          `block` blob NOT NULL,
          PRIMARY KEY (`previous_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
    }

}

?>