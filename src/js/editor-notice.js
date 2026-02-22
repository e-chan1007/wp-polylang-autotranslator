(function (wp) {
  const { apiFetch } = wp;
  const { subscribe, select, dispatch } = wp.data;

  let wasSaving = false;
  let isProcessing = false;

  subscribe(async () => {
    if (isProcessing) return;

    const editor = select("core/editor");
    if (!editor) return;

    const isSaving = editor.isSavingPost();

    if (wasSaving && !isSaving) {
      isProcessing = true;
      const restBase = wp.data.select("core").getPostType(wp.data.select("core/editor").getCurrentPostType()).rest_base;
      const { meta } = await apiFetch({ path: `/wp/v2/${restBase}/${editor.getCurrentPostId()}?_fields=meta` }).catch(() => ({}));
      const error = meta ? meta["_auto_translate_error"] : null;

      if (error) {
        dispatch("core/notices").createNotice(
          "error",
          error,
          {
            isDismissible: true,
            type: "snackbar",
          }
        );
      }
      isProcessing = false;
    }

    wasSaving = isSaving;
  });
})(window.wp);
