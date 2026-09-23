/**
 * System Data wordcloud fetch gate (Part 04.1).
 * When systemIncluded is false (csv_only), endpoints must not be called.
 */

export async function fetchSystemWordcloudsIfIncluded({
  eventId,
  systemIncluded,
  getWordcloud,
}) {
  if (!eventId || systemIncluded === false) {
    return { requested: false, feedback: null, products: null };
  }

  const [feedbackRes, productsRes] = await Promise.allSettled([
    getWordcloud('feedback', eventId),
    getWordcloud('products', eventId),
  ]);

  return {
    requested: true,
    feedback: feedbackRes,
    products: productsRes,
  };
}
