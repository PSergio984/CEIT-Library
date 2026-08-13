# Project: CEIT-Library Improvements

## Overview
This project focuses on improving the CEIT-Library system by fixing critical frontend bugs and enhancing security. The system is a Laravel 12 application using Livewire 3 and Mary UI.

## Goals
- Upgrade to Livewire v4 and modernize dependencies.
- Fix frontend interaction bugs (e.g., modals locking UI).
- Resolve mobile camera issues in the QR scanner.
- Enhance security across the application.
- Improve overall stability and test coverage.
- Give the system an AI librarian assistant backed by a RAG pipeline.

## Tech Stack
- Laravel 12
- Livewire v4 / Volt
- Mary UI v2 (Tailwind CSS v4 / daisyUI v5)
- PHPUnit
- Python sidecar service (hybrid BM25 + semantic search, FastAPI)
- LLM API (provider TBD)
- Grafana + Prometheus (monitoring)

## Current Milestone: v2.0 AI Assistant

**Goal:** Give CEIT-Library an AI librarian — a RAG agent that finds books, answers questions with citations, searches academic papers, answers library-policy questions, and summarizes — through an in-app chat widget.

**Target features:**
- Hybrid search (BM25 + semantic, RRF fusion) over the library catalog
- Conversational chat with source citations
- Academic paper search
- Library policy Q&A (FAQ corpus)
- Summarization
- Livewire chat widget (role-aware)
- Full evaluation stack (ground truth, LLM-as-judge) + Grafana monitoring

**Reference codebases:**
- `rag-search-engine` (~/workspace in WSL) — hybrid BM25 + semantic algorithm, RRF fusion, citations/summarize/question CLI
- `llm-zc` (D:\ai-eng\llm-zc) — evaluation harness, agentic orchestration flows, monitoring, feedback DB

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `$gsd-transition`):
1. Requirements invalidated? → Move to Out of Scope with reason
2. Requirements validated? → Move to Validated with phase reference
3. New requirements emerged? → Add to Active
4. Decisions to log? → Add to Key Decisions
5. "What This Is" still accurate? → Update if drifted

**After each milestone** (via `$gsd-complete-milestone`):
1. Full review of all sections
2. Core Value check — still the right priority?
3. Audit Out of Scope — reasons still valid?
4. Update Context with current state

---
Last updated: 2026-08-13 (Milestone v2.0 started)
