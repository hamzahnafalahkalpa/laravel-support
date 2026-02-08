<?php

namespace Hanafalah\LaravelSupport\Contracts\Dashboard;

/**
 * Interface for dashboard metric definitions.
 *
 * Implementations define the presentation data for metrics
 * that are stored as minimal data in Elasticsearch.
 */
interface DashboardMetricDefinition
{
    /**
     * Get the unique identifier for this metric.
     */
    public function getId(): string;

    /**
     * Get the display label for this metric.
     */
    public function getLabel(): string;

    /**
     * Get the icon identifier for this metric.
     */
    public function getIcon(): string;

    /**
     * Get the primary color for this metric.
     */
    public function getColor(): string;

    /**
     * Get all presentation data for this metric.
     *
     * @return array{
     *     id: string,
     *     label: string,
     *     icon: string,
     *     color: string,
     *     gradient?: string,
     *     bg_light?: string,
     *     text_color?: string,
     *     border_color?: string,
     *     is_currency?: bool,
     *     link?: string
     * }
     */
    public function getPresentationData(): array;
}
