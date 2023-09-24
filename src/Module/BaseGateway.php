<?php

/**
 * BaseGateway class file
 *
 * @package WHMCS Utils
 */

namespace Oblak\WHMCS\Module;

use Exception;
use WHMCS\Database\Capsule;
use WHMCS\Invoice;

/**
 * Base Third Party Gateway Module
 */
abstract class BaseGateway extends BaseModule
{
    /**
     * Gateway module meta data
     *
     * @var array
     */
    protected readonly array $meta;

    /**
     * {@inheritDoc}
     */
    protected function __construct()
    {
        $this->meta = $this->getMetadata();

        parent::__construct();
    }

    /**
     * Gateways load the settings via WHMCS native function
     *
     * @return array
     */
    protected function loadSettings(): array
    {
        return getGatewayVariables($this->moduleName);
    }

    /**
     * Get the Gateway module MetaData
     *
     * @return array
     */
    abstract public static function getMetadata(): array;

    public function getTransactions(int $invoiceId): ?array
    {
        $invoice = new Invoice();
        try {
            $invoice->setID($invoiceId);
            return array_filter(
                $invoice->getTransactions(),
                fn($transaction) => $transaction['gateway'] == $this->settings['name']
            );
        } catch (Exception $e) {
            logModuleCall($this->moduleName, 'getTransactions', $invoiceId, $e->getMessage(), $e->getTraceAsString());
            return null;
        }
    }

    /**
     * Get the gateway logs for this gateway
     *
     * @return array
     */
    public function getGatewayLogs(): array
    {
        $logs    = [];
        $rawLogs = Capsule::table('tblgatewaylog')
            ->where('gateway', $this->settings['name'])
            ->orderBy('id', 'desc')
            ->get();

        foreach ($rawLogs as $log) {
            $logs[] = [
                'id' => $log->id,
                'date' => $log->date,
                'gateway' => $log->gateway,
                'data' => json_decode($log->data, true),
            ];
        }

        return $logs;
    }

    /**
     * Get a gateway log for a specific transaction
     *
     * @param  string     $transactionId The transaction ID
     * @return array|null                Gateway log
     */
    public function getGatewayLog(string $transactionId): ?array
    {
        $logs = array_filter(
            $this->getGatewayLogs(),
            fn($log) => $this->getTransactionId($log) == $transactionId
        );

        return array_shift($logs);
    }

    /**
     * Returns a transaction ID for the transaction log
     *
     * @param array $log The transaction log
     * @return string    The transaction ID
     */
    abstract protected function getTransactionId(array $log): string;

    /**
     * Main third party gateway callback function
     *
     * This function is called by WHMCS when the gateway sends a callback to WHMCS
     */
    public function checkResponse(): void
    {
        if (empty($_POST)) {
            die('POST SHOULD NOT BE EMPTY');
        }

        $data = $this->unslash($_POST);

        $invoiceId = $this->getInvoiceId($data);
        $invoice   = new \WHMCS\Invoice($invoiceId);

        $duplicate = array_filter(
            $this->getTransactions($invoiceId),
            fn($transaction) => $transaction['transid'] == $this->getTransactionId($data)
        );


        if (count($duplicate) > 0 && $invoice->getData('status') == 'Paid') {
            $this->handleRedirect(true, $invoiceId);
        }

        $this->gatewayCallback($data, $invoiceId);
    }

    /**
     * Gateway callback function to be implemented by the gateway
     *
     * @param  array $data      Gateway callback data
     * @param  int   $invoiceId Invoice ID
     */
    abstract protected function gatewayCallback(array $data, int $invoiceId): void;

    /**
     * Get the invoice id based on gateway response
     *
     * You can use WHMCS native `checkCbInvoiceId` function to check the invoice ID
     *
     * @param  array $data Gateway response
     * @return int         Invoice ID
     */
    abstract protected function getInvoiceId(array $data): int;


    /**
     * Process the transaction data.
     *
     * Does the following:
     *  * Logs the transaction
     *  * Sends an email to the client
     *
     * @param bool  $transactionStatus Whether the transaction was successful
     * @param array $data              Transaction data
     * @param int   $invoiceId         Invoice ID
     */
    protected function processTransaction(bool $transactionStatus, array $data, int $invoiceId)
    {
        logTransaction($this->settings['name'], json_encode($data), $transactionStatus ? 'Success' : 'Failed');
        $this->sendEmail($transactionStatus, $invoiceId);
    }

    /**
     * Redirects the user to the invoice page.
     *
     * @param bool $transactionStatus Whether the transaction was successful
     * @param int  $invoiceId         Invoice ID
     * @param bool $noQs              Whether to remove query string to the URL
     */
    protected function handleRedirect(bool $transactionStatus, int $invoiceId, bool $noQs = false)
    {
        $redirUrl = sprintf(
            'id=%d',
            $invoiceId,
        );

        if (!$noQs) {
            $redirUrl .= sprintf(
                '&%s=true',
                $transactionStatus ? 'paymentsuccess' : 'paymentfailed',
            );
        }

        redirSystemURL($redirUrl, "viewinvoice.php");
        return;
    }

    /**
     * Sends an email to the client
     *
     * @param bool $transactionStatus Whether the transaction was successful
     * @param int  $invoiceId         Invoice ID
     */
    protected function sendEmail(bool $transactionStatus, int $invoiceId)
    {
        $templateName  = $this->meta[$transactionStatus ? 'successEmail' : 'failedEmail'];
        $emailTemplate = \WHMCS\Mail\Template::where("name", "=", $templateName)->first()->name;

        if (!$emailTemplate) {
            return;
        }

        logModuleCall(
            $this->moduleName,
            $transactionStatus ? 'successEmail' : 'failedEmail',
            $emailTemplate,
            $this->settings,
        );

        sendMessage($emailTemplate, $invoiceId);
    }

    /**
     * Unslashes the posted data
     *
     * @param  mixed $value The value to unslash
     * @return mixed        The unslashed value
     */
    protected function unslash(mixed $value): mixed
    {
        return $this->mapDeep($value, fn($value) => is_string($value) ? stripslashes($value) : $value);
    }

    /**
     * Maps a function to all non-iterable elements of an array or an object.
     *
     * This is similar to `array_walk_recursive()` but acts upon objects too.
     *
     * @param mixed    $value    The array, object, or scalar.
     * @param callable $callback The function to map onto $value.
     * @return mixed The value with the callback applied to all non-arrays and non-objects inside it.
     */
    protected function mapDeep($value, $callback)
    {
        if (is_array($value)) {
            foreach ($value as $index => $item) {
                $value[ $index ] = $this->mapDeep($item, $callback);
            }
        } elseif (is_object($value)) {
            $objectVars = get_object_vars($value);
            foreach ($objectVars as $propName => $propValue) {
                $value->$propName = $this->mapDeep($propValue, $callback);
            }
        } else {
            $value = call_user_func($callback, $value);
        }

        return $value;
    }
}
