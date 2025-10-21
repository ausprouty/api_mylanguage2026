<?php
Namespace App\Interfaces;

interface ArclightVideoInterface {
    public function getVideoSource(): ?string;
    public function getVideoPrefix(): ?string;
    public function getVideoCode(): ?string;
    public function getVideoSegment(): ?string;
    public function getStartTime(): ?int;
    public function getEndTime(): ?int;
}
