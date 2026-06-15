export const extractApiError = (error) => {
  const data = error?.response?.data;
  if (!data) return 'Something went wrong. Please try again.';

  if (typeof data.message === 'string') return data.message;

  if (data.errors && typeof data.errors === 'object') {
    const firstKey = Object.keys(data.errors)[0];
    const firstMessage = data.errors[firstKey]?.[0];
    if (firstMessage) return firstMessage;
  }

  return 'Something went wrong. Please try again.';
};
