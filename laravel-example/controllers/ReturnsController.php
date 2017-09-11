<?php

namespace App\Http\Controllers\Api\v2_0;

use App\Concerns\SendsEmails,
    App\Http\Controllers\ResourceController,
    App\Services\Payments\Processor,
    App\Services\Payments\PaymentRequiredException,
    Illuminate\Http\Request;

/**
 * RESTful controller for the returns resource.
 */
class ReturnsController extends ResourceController {
    use SendsEmails;

    protected $returnInvoiceChanged = false;

    // === Public Methods === //

    /**
     * Check if payment completed successfully and if so, proceed with label creating. Otherwise, return error as JSON.
     * @param int $rma
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Foundation\Validation\ValidationException
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function createLabel($rma, Request $request) {
        $this->validate($request, ['timestamp' => 'required|integer']);
        $returnInvoice = $this->getReturn($rma, $request->input('timestamp'));
        abort_if($returnInvoice->isFinalized(), \Http::STATUS_CONFLICT, 'Return invoice is already finalized.');
        if (\AdminDAO::isMerchantAuthorizationRequired() && $returnInvoice->getReviewStatus() != \Order::REVIEW_STATUS_ACCEPTED) {
            abort(\Http::STATUS_FORBIDDEN, $returnInvoice->getReviewStatusMessage());
        }
        try {
            $returnProcessor = new \ReturnProcessor();
            if (!$returnInvoice->getCustomDataField('bypass_payment') && $returnInvoice->getPaymentOption()) {
                $shippingRate = $returnProcessor->checkShippingRate($returnInvoice);
                Processor::get($returnInvoice->getPaymentOption())->checkPayment($request->input('payment_id'), $shippingRate);
            }
            // Generate label and update order quantities.
            /** @var \Doctrine\ORM\EntityManager $em */
            global $em;
            $conn = $em->getConnection();
            $conn->beginTransaction();

            try {
                // Ship the return and create a label.
                $returnProcessor->finalizeReturn($returnInvoice);
            } catch (\Exception $e) {
                $conn->rollback();
                abort(\Http::STATUS_INTERNAL_SERVER_ERROR, "Error processing return: " . $e->getMessage());
            }

            try {
                // Upload the label to Amazon.
                \ReturnInvoiceDAO::processLabel($returnInvoice);
                // Flush the return invoice to the DB, but don't convert it to a return order yet. We'll do that outside of the transaction, in case the return order creation fails. It can be created from the return invoice later.
                \ReturnInvoiceDAO::finalizeReturn($returnInvoice, true, false);
            } catch (\Exception $e) {
                \Log::exception($e);
                // The exception is logged in the ReturnInvoiceDAO::processLabel().
                // If label wasn't saved, the shipment should be voided.
                $conn->rollback();
                try {
                    $returnProcessor->voidReturn($returnInvoice);
                } catch (\Exception $e) {
                    // The exception is logged in the ReturnProcessor::voidReturn().
                }
                abort(\Http::STATUS_INTERNAL_SERVER_ERROR, "Error processing return: couldn't save shipping label.");
            }

            $conn->commit();

            try {
                \ReturnInvoiceDAO::convertAndUploadToRc($returnInvoice);
            } catch (\Exception $e) {
                // The exception is already logged in ReturnInvoiceDAO::convertAndUploadToRc(). Continue as usual: the return order can be created from the return invoice later.
            }

            // Go ahead and show the label.
            \FrontEndSession::load()
                ->setReturnLabelRmaNumber($returnInvoice->getRmaNumber())
                // Tidy up the session; will reload return info on next page using RMA number.
                ->removeReturnInvoiceForm()
                ->removeFromAddressId()
                ->removeRmaNumber()
                ->removeReturnId()
                ->removeReturnTime();
            // Also tidy up the admin session.
            \AdminSession::load()
                ->removeReturnInvoiceForm()
                ->removeFromAddressId()
                ->removeRmaNumber()
                ->removeReturnId()
                ->removeReturnTime();

            $labelUrl = "/returnlabel.php?id={$returnInvoice->returnId}&t={$returnInvoice->time}";
            // FIXME Once D1973 is landed this will return an empty response: return \Response::json(new \stdClass(), \Http::STATUS_CREATED);
            return \Response::json(['redirect' => $labelUrl], \Http::STATUS_CREATED);
        } catch (\UpsShippingException $e) {
            \Log::exception($e);
            abort(\Http::STATUS_INTERNAL_SERVER_ERROR, $e->getMessage());
        } catch (\EndiciaShippingException $e) {
            \Log::exception($e);
            abort(\Http::STATUS_INTERNAL_SERVER_ERROR, $e->getPrevious()->getMessage());
        } catch (PaymentRequiredException $e) {
            \Log::exception($e);
            abort(\Http::STATUS_PAYMENT_REQUIRED, $e->getMessage());
        } catch (\Exception $e) {
            \Log::exception($e);
            abort(\Http::STATUS_INTERNAL_SERVER_ERROR, 'Unable to process return.');
        }
    }

    /**
     * Sends an email related to a return.
     * @param Request $request
     * @param integer $rma The RMA.
     * @return \Illuminate\Http\JsonResponse Response.
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException on error.
     * @throws \Illuminate\Validation\ValidationException on validation error.
     */
    public function email(Request $request, $rma) {
        $this->validate($request, ['email_type' => 'required', 'timestamp' => 'required|integer']);

        switch ($request->input('email_type')) {
            case 'customer-submitted':
                $returnInvoice = $this->getReturn($rma, $request->input('timestamp'));
                $returnProcessor = new \ReturnProcessor();
                $returnProcessor->sendReviewEmail($returnInvoice);
                return \Response::json(new \stdClass(), \Http::STATUS_CREATED);
            default:
                abort(\Http::STATUS_BAD_REQUEST, 'Invalid email type: ' . var_export($request->input('email_type'), true));
        }
    }

    /**
     * Update return invoice.
     * @param Request $request
     * @param integer $rma The RMA.
     * @return \Illuminate\Http\Response
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException on error.
     * @throws \Illuminate\Validation\ValidationException on validation error.
     */
    public function update(Request $request, $rma) {
        $this->validate($request, ['timestamp' => 'required|integer']);
        $returnInvoice = $this->getReturn($rma, $request->input('timestamp'));
        $this->setReviewStatus($request, $returnInvoice);

        // For other PATCH requests, update the relevant fields in helper methods similar to setReviewStatus(). Call those methods here.

        if ($this->returnInvoiceChanged) {
            try {
                \ReturnInvoiceDAO::update($returnInvoice);
            } catch (\Exception $e) {
                \Log::exception($e);
                abort(\Http::STATUS_INTERNAL_SERVER_ERROR, 'Could not update return invoice.');
            }
        }

        $this->sendEmails();

        return response('', \Http::STATUS_NO_CONTENT);
    }

    // === Protected Methods === //

    /**
     * Get return invoice.
     * @param integer $rma The RMA.
     * @param integer $timestamp The timestamp.
     * @return \ReturnInvoice
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException if not found.
     */
    protected function getReturn($rma, $timestamp) {
        $returnInvoice = \ReturnInvoiceDAO::findByRma($rma, $timestamp);
        abort_if(!$returnInvoice, \Http::STATUS_NOT_FOUND, 'Return invoice not found: ' . var_export($rma, true) . ' with timestamp ' . var_export($timestamp, true) . '.');
        return $returnInvoice;
    }

    /**
     * Set return invoice status.
     * @param Request $request
     * @param ReturnInvoice $returnInvoice The return invoice.
     * @throws \Illuminate\Validation\ValidationException on validation error.
     */
    protected function setReviewStatus(Request $request, \ReturnInvoice $returnInvoice) {
        if (!$request->exists('review_status')) {
            return;
        }
        $this->validate($request, ['review_status' => 'reviewStatus']);

        $status = $request->input('review_status');

        $returnInvoice->setReviewedAt(new \DateTime());
        $returnInvoice->setReviewedBy(\AdminSession::load()->getUser()->getFullName());
        $returnInvoice->setReviewStatus($status);
        if ($request->exists('review_comment')) {
            $returnInvoice->setReviewComment($request->input('review_comment'));
        }
        $this->returnInvoiceChanged = true;

        if ($status == \Order::REVIEW_STATUS_ACCEPTED) {
            $emailType = \Emailer::TYPE_RETURN_REVIEW_ACCEPTED;
            if (app('flow')->createsShippingLabel()) {
                $message = "To review your return and complete the process, click the link below:";
            } else {
                $message = "To review your return, click the link below:";
            }
            $subject = "Return accepted";
        } elseif ($status == \Order::REVIEW_STATUS_REJECTED) {
            $emailType = \Emailer::TYPE_RETURN_REVIEW_REJECTED;
            $message = "To review your return, click the link below:";
            $subject = "Return rejected";
        } else {
            // Since the status passed validation, it can only be 'pending' at this point.
            $returnInvoice->setReviewedAt(null)->setReviewedBy(null);
            return;
        }

        $this->addEmail([
            'email_type' => $emailType,
            'to' => $returnInvoice->getCustomerEmail(),
            'params' => [
                'contactName' => $returnInvoice->getCustomerAddress()->getContact(),
                'content' => $message,
                'reviewComment' => $returnInvoice->getReviewComment() !== null ? nl2br(cleanForHtml($returnInvoice->getReviewComment())) : null,
                'reviewStatus' => \OrderDAO::getReviewStatusTextForEmail($status),
                'reviewUrl' => url("/return_confirm.php?id={$returnInvoice->getId()}&t={$returnInvoice->time}"),
                'requestNumber' => $returnInvoice->getOrder()->getOrderNumber(),
                'rmaNumber' => $returnInvoice->getRmaNumber(),
                'subject' => $subject,
                'type' => 'return',
                'warehouseName' => $returnInvoice->getWarehouse() ? $returnInvoice->getWarehouse()->getName() : null
            ]
        ]);
    }
}
