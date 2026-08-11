const timeZone = "America/Tegucigalpa";

export const hondurasDate = (value?: string | null) =>
    value
        ? new Intl.DateTimeFormat("es-HN", { dateStyle: "medium", timeZone }).format(new Date(value))
        : "Sin fecha";

export const hondurasDateTime = (value?: string | null) =>
    value
        ? new Intl.DateTimeFormat("es-HN", { dateStyle: "medium", timeStyle: "short", timeZone }).format(new Date(value))
        : "Sin fecha";
