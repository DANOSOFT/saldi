<?php
// --- includes/constants/SagerPermission.php --- ver 5.1.1 --- 2026-11-24 ---
// Copyright (c) 2026 Danosoft ApS
// SPDX-License-Identifier: GPL-2.0-or-later
// 20261124 CDX/NTR Name and document the eight employee-group permissions used by Sager.
// 20261124 CDX/NTR Use integer class constants directly in legacy offset checks.

/**
 * Positions in the eight-character Sager rights string ($sag_rettigheder).
 *
 * Values are zero-based substr() offsets: value 6 means the seventh character.
 * The group editor writes '1' for checked and '0' for unchecked permissions.
 * Existing checks use PHP string truthiness, so a missing character also denies access.
 *
 * Source: brugere.ansat_id -> ansatte.gruppe -> grupper.id, with the string in grupper.box2
 * (art = 'brgrp'). includes/online.php loads it into $sag_rettigheder.
 * Configure it under Sager -> Ansatte -> Brugergrupper (sager/ansatte.php?funktion=brugergrupper).
 *
 * These are separate from brugere.rettigheder and the role_permissions registry managed by
 * systemdata/usersRoles.php. Assigning a role there does not set these group permissions.
 * The descriptions below name the group editor's checkboxes and existing uses; individual
 * pages still decide which checks to enforce. These constants do not grant permissions themselves.
 *
 * @see brugergrupper()
 */
final class SagerPermission
{
    /**
     * Dansk: Login | English: Login
     * Offset 0, character 1. Used by includes/login.php to choose the payroll landing page.
     */
    public const LOGIN = 0;

    /**
     * Dansk: Opret/ret brugergrupper | English: Create/edit user groups
     * Offset 1, character 2. Stored by the Sager group editor for group administration.
     */
    public const MANAGE_USER_GROUPS = 1;

    /**
     * Dansk: Opret/ret sag | English: Create/edit cases
     * Offset 2, character 3. Also used for case navigation, planning and certification links.
     */
    public const MANAGE_CASES = 2;

    /**
     * Dansk: Opret/ret ansat | English: Create/edit employees
     * Offset 3, character 4. Existing menus also use this for accounting and payroll links,
     * even though the group editor stores a separate ACCOUNTING permission at offset 7.
     */
    public const MANAGE_EMPLOYEES = 3;

    /**
     * Dansk: Opret/ret kunde | English: Create/edit customers
     * Offset 4, character 5. Also used to show customer navigation in the Sager menus.
     */
    public const MANAGE_CUSTOMERS = 4;

    /**
     * Dansk: Opret/ret notat | English: Create/edit notes
     * Offset 5, character 6. Also used to show Dagbog (Diary) in the Sager menus.
     */
    public const MANAGE_NOTES = 5;

    /**
     * Dansk: Godkend akkordseddel | English: Approve piecework slips
     * Offset 6, character 7. The approver permission also controls editing payroll rates
     * (Ret satser), approval/rejection actions and the overtime report menu and page.
     */
    public const APPROVE_PIECEWORK = 6;

    /**
     * Dansk: Regnskab | English: Accounting
     * Offset 7, character 8. Stored by the Sager group editor; existing accounting menu
     * links check MANAGE_EMPLOYEES (offset 3), rather than this position.
     */
    public const ACCOUNTING = 7;
}
