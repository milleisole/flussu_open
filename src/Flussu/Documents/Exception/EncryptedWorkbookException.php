<?php
/* --------------------------------------------------------------------*
 * Flussu v5.x - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * Signals an XLSX/ODS that is encrypted (password-protected) or that
 * is wrapped in the OLE Compound File container (legacy .xls binary,
 * or .xls renamed to .xlsx). The DocumentSpace catches this typed
 * exception separately from generic extraction failures and routes the
 * upload endpoint to emit HTTP 422 reason=encrypted_workbook instead
 * of registering a placeholder.
 * -------------------------------------------------------*/

namespace Flussu\Documents\Exception;

class EncryptedWorkbookException extends \RuntimeException
{
}
