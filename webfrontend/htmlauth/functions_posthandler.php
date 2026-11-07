<?php
function postHandler($post) {
    global $lbpconfigdir, $lbpdatadir, $L, $loxApp3json;

    # --- Löschen ---
    if (!empty($post['delete-mapping'])) {
        $name = $post['delete-mapping'];
        $mjson = new LBJSON("$lbpdatadir/mappings.json");

        if (isset($mjson->slave->$name)) {
            unset($mjson->slave->$name);
            $mjson->write();
            return "";
        }
        return "<font color=red>" . ($L['FORM1.NOT_FOUND'] ?? "Mapping nicht gefunden") . ": $name</font>";
    }

    # --- Speichern (neues Mapping oder Update) ---
    if (!empty($post['hidden-sonnen']) && !empty($post['hidden-control'])) {
        $name = $post['hidden-sonnen'];
        $uuid = $post['hidden-control'];

        if (strlen($uuid) != 35) {
            return "<font color=red>" . ($L['FORM1.UUID_INVALID'] ?? "Ungültige UUID") . "</font>";
        }

        # --- Control-Typ zur UUID nachschlagen ---
        $ctrlType = "";
        foreach (($loxApp3json['controls']) as $c) {
            if (($c['uuidAction'] ?? "") === $uuid) {
                $ctrlType = str_replace("InfoOnly", "", $c['type']);
                break;
            }
        }

        $mjson = new LBJSON("$lbpdatadir/mappings.json");
        $mjson->slave->$name = (object) array(
            "Name"              => $name,
            "LoxoneControlUUID" => $uuid,
            "LoxoneControlType" => $ctrlType
        );
        $mjson->write();

        return "";
    }

    return "";
}
?>