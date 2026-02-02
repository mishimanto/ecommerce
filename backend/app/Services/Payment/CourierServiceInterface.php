<?php

namespace App\Services\Courier;

interface CourierServiceInterface
{
    /**
     * Create shipment
     */
    public function createShipment($orderData);

    /**
     * Track shipment
     */
    public function trackShipment($trackingNumber);

    /**
     * Calculate shipping cost
     */
    public function calculateShipping($data);

    /**
     * Cancel shipment
     */
    public function cancelShipment($trackingNumber, $reason = null);

    /**
     * Get available services
     */
    public function getServices();

    /**
     * Get areas/cities
     */
    public function getAreas();
}