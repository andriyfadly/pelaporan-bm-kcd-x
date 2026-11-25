/** Buka PDF dokumen yang baru disimpan; flash `dokumen_id` diisi controller. */
export const bukaPdfDokumen = (page: { props: Record<string, unknown> }): void => {
    const flash = page.props.flash as { dokumen_id?: string | null } | undefined;
    if (flash?.dokumen_id) {
        window.open(`/pencatatan-bm/dokumen/${flash.dokumen_id}/pdf`, '_blank');
    }
};
